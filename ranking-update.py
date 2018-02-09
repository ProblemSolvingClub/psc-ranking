#!/usr/bin/env python3
import collections, functools, json, logging, lxml.html, os, re, sqlite3, traceback, urllib.error, urllib.request

# This assumes that ranking.sqlite3 is in the same folder as this script.
dir_path = os.path.dirname(os.path.realpath(__file__))
db_path = os.path.join(dir_path + '/ranking.sqlite3')

# Kattis seems to block urllib user agent
kattis_user_agent = 'Mozilla/5.0 (X11; CrOS x86_64 8350.68.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36'

# Connect to database.
db = sqlite3.connect(db_path)
db.row_factory = sqlite3.Row

def update_solved(site_id, username, solved):
    cursor = db.execute('INSERT INTO site_score (site_id, username, solved) VALUES (?, ?, ?)', 
            (int(site_id), username, solved))
    if cursor.rowcount == 0:
        raise RuntimeError('Failed to update solved %s %s %s' % (site_id, username, solved))

def get_http(url):
    return urllib.request.urlopen(url, timeout=10).read() # 10 second timeout

def scrape_codeforces(site_id, username_userid):
    # I don't see a better way than scanning all submissions of the user
    for username in username_userid.keys():
        body_bytes = get_http('http://www.codeforces.com/api/user.status?handle=%s' % username)
        doc = json.loads(body_bytes.decode('utf-8'))
        solved = set()
        for obj in doc['result']:
            # I'm assuming that (contestId, index) is a unique identifier for the problem
            if obj['verdict'] == 'OK': solved.add((obj['problem']['contestId'], obj['problem']['index']))
        update_solved(site_id, username, len(solved))

def scrape_codechef(site_id, username_userid):
    for username in username_userid.keys():
        tree = lxml.html.fromstring(get_http('https://www.codechef.com/users/%s' % username))
        solved = tree.cssselect("#problem_stats tr:nth-child(2) td")[0].text
        update_solved(site_id, username, solved)

def scrape_coj(site_id, username_userid):
    for username in username_userid.keys():
        tree = lxml.html.fromstring(get_http('http://coj.uci.cu/user/useraccount.xhtml?username=%s' % username))
        solved = tree.cssselect("div.panel-heading:contains('Solved problems') span.badge")[0].text
        update_solved(site_id, username, solved)

def scrape_kattis(site_id, username_userid):

    # First, get users who are listed as University of Calgary
    # This reduces the number of requests needed
    req = urllib.request.Request('https://open.kattis.com/universities/ucalgary.ca')
    req.add_header('User-Agent', kattis_user_agent)
    tree = lxml.html.fromstring(get_http(req))
    solved = tree.cssselect('.table-kattis tbody tr')
    for tr in solved:
        username = tr.cssselect('a')[0].get('href').split('/')[-1]
        score = float(tr.cssselect('td:last-child')[0].text)
        if username in username_userid.keys():
            update_solved(site_id, username, score)
            username_userid.pop(username)

    # Then get other users
    for username in username_userid.keys():
        req = urllib.request.Request('https://open.kattis.com/users/%s' % username)
        req.add_header('User-Agent', kattis_user_agent)
        try:
            tree = lxml.html.fromstring(get_http(req))
            score = float(tree.cssselect('.rank tr:nth-child(2) td:nth-child(2)')[0].text)
            update_solved(site_id, username, score)
        except urllib.error.HTTPError:
            logging.exception('Failed to fetch Kattis user "%s"', username)

def scrape_poj(site_id, username_userid):
    for username in username_userid.keys():
        tree = lxml.html.fromstring(get_http('http://poj.org/userstatus?user_id=%s' % username))
        solved = tree.cssselect("tr:contains('Solved:') a")[0].text
        update_solved(site_id, username, solved)

def scrape_spoj(site_id, username_userid):
    for username in username_userid.keys():
        tree = lxml.html.fromstring(get_http('http://www.spoj.com/users/%s/' % username))
        solved = tree.cssselect('.profile-info-data-stats dd')[0].text
        update_solved(site_id, username, solved)

def scrape_uva(base_url, site_id, username_userid):
    # uhunt has a weird API where it returns a list of bitsets of solved problems
    body_bytes = get_http('%s/api/solved-bits/%s' % (base_url, ','.join(username_userid.keys())))
    doc = json.loads(body_bytes.decode('utf-8'))
    for obj in doc:
        count = 0
        for bs in obj['solved']:
            while bs:
                if bs & 1: count += 1
                bs >>= 1
        update_solved(site_id, str(obj['uid']), count)

KattisContest = collections.namedtuple('KattisContest', 'name is_over solved')
def parse_kattis_contest_html(html):
    tree = lxml.html.fromstring(html)
    contest_name = tree.cssselect('h2.title')[0].text

    # Determine columns that represent problems
    cell_to_problem_id = {}
    col = 0
    for th in tree.cssselect('#standings thead tr th'):
        class_attr = th.get('class')
        if class_attr and 'problemcolheader-standings' in class_attr: # NOTE: This does not do a proper class check
            problem_id = th.cssselect('a')[0].get('href').split('/')[-1]
            cell_to_problem_id[col] = problem_id
        colspan = th.get('colspan')
        if not colspan: colspan = '1'
        col += int(colspan)

    solved = {}
    for tr in tree.cssselect('#standings tr'):
        user_a = tr.cssselect('a')
        if not user_a: continue # not a user row
        user_href = user_a[0].get('href')
        user_match = re.match(r'^/users/(.+)$', user_href)
        if not user_match: continue # not a user row
        kattis_user = user_href.split('/')[-1]
        col = 0
        for td in tr.cssselect('td'):
            if col in cell_to_problem_id:
                problem_id = cell_to_problem_id[col]
                class_attr = td.get('class')
                if class_attr and 'solved' in class_attr: # NOTE: This does not do a proper class check
                    solved.setdefault(kattis_user, []).append(problem_id)
            colspan = td.get('colspan')
            if not colspan: colspan = '1'
            col += int(colspan)

    is_over = 'session-finished' in tree.cssselect('.contest-progress')[0].get('class')
    return KattisContest(name=contest_name, is_over=is_over, solved=solved)

SupportedSite = collections.namedtuple('SupportedSite', 'id name scrape_func')
supported_sites = [
    SupportedSite(id=1, name='Caribbean Online Judge', scrape_func=scrape_coj),
    SupportedSite(id=2, name='CodeChef', scrape_func=scrape_codechef),
    SupportedSite(id=3, name='Codeforces', scrape_func=scrape_codeforces),
    SupportedSite(id=4, name='ICPC Live Archive', scrape_func=functools.partial(scrape_uva, 'https://icpcarchive.ecs.baylor.edu/uhunt')),
    SupportedSite(id=5, name='Kattis', scrape_func=scrape_kattis),
    SupportedSite(id=6, name='Peking Online Judge', scrape_func=scrape_poj),
    SupportedSite(id=7, name='Sphere Online Judge', scrape_func=scrape_spoj),
    SupportedSite(id=8, name='UVa Online Judge', scrape_func=functools.partial(scrape_uva, 'http://uhunt.felix-halim.net')),
]

# Scrape users on sites
for site in supported_sites:
    print('Processing %s' % site.name)
    username_userid = {}
    for row in db.execute('SELECT user_id, username FROM site_account WHERE site_id=?', (site.id,)):
        username_userid[str(row['username'])] = row['user_id']
    try:
        site.scrape_func(site.id, username_userid)
    except:
        logging.exception('Fatal error occured while scraping %s', site.name)

# Scrape Kattis contests that have not been scraped or were not yet finished at the last scrape.
for row in db.execute('SELECT kattis_contest_id AS k FROM meeting WHERE kattis_contest_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM kattis_contest WHERE kattis_contest_id=k AND is_over)'):
    print('Scraping Kattis content %s' % row['k'])
    req = urllib.request.Request('https://open.kattis.com/contests/%s' % row['k'])
    req.add_header('User-Agent', kattis_user_agent)
    html = get_http(req)
    contest = parse_kattis_contest_html(html)

    # Insert HTML to database so we have it
    db.execute('INSERT OR REPLACE INTO kattis_contest (kattis_contest_id, kattis_contest_name, html, is_over) VALUES (?, ?, ?, ?)', (row['k'], contest.name, html, contest.is_over))

    # Insert solved problems for each user.
    for username, solved in contest.solved.items():
        for problem_id in solved:
            db.execute('INSERT OR IGNORE INTO kattis_contest_solved (kattis_contest_id, kattis_username, kattis_problem_id) VALUES (?,?,?)', (row['k'], username, problem_id))

db.commit()
