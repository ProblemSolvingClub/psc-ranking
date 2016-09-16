#!/usr/bin/env python3
import collections, functools, json, lxml.etree, lxml.html, os, sqlite3, urllib.request

# This assumes that ranking.sqlite3 is in the same folder as this script.
dir_path = os.path.dirname(os.path.realpath(__file__))
db_path = os.path.join(dir_path + '/ranking.sqlite3')

# Connect to database.
db = sqlite3.connect(db_path)
db.row_factory = sqlite3.Row

def update_solved(site_id, user_id, solved):
    db.execute('INSERT INTO site_score (user_id, site_id, solved) VALUES (?, ?, ?)', 
            (user_id, site_id, solved))

def scrape_codeforces(site_id, usernames):
    # I don't see a better way than scanning all submissions of the user
    for username in usernames:
        body_bytes = urllib.request.urlopen('http://www.codeforces.com/api/user.status?handle=%s' % username).read()
        doc = json.loads(body_bytes.decode('utf-8'))
        solved = set()
        for obj in doc['result']:
            # I'm assuming that (contestId, index) is a unique identifier for the problem
            if obj['verdict'] == 'OK': solved.add((obj['problem']['contestId'], obj['problem']['index']))
        update_solved(site_id, username, len(solved))

def scrape_codechef(site_id, usernames):
    for username in usernames:
        req = urllib.request.Request('https://www.codechef.com/users/%s' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect("#problem_stats tr:nth-child(2) td")[0].text
        update_solved('codechef', username, solved)

def scrape_coj(site_id, usernames):
    for username in usernames:
        req = urllib.request.Request('http://coj.uci.cu/user/useraccount.xhtml?username=%s' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect("div.panel-heading:contains('Solved problems') span.badge")[0].text
        update_solved(site_id, username, solved)

def scrape_kattis(site_id, usernames):
    # Kattis seems to block urllib user agent
    user_agent = 'Mozilla/5.0 (X11; CrOS x86_64 8350.68.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36'
    usernames = set(usernames)

    # First, get users who are listed as University of Calgary
    # This reduces the number of requests needed
    req = urllib.request.Request('https://open.kattis.com/universities/ucalgary.ca')
    req.add_header('User-Agent', user_agent)
    tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
    solved = tree.cssselect('.table-kattis tbody tr')
    for tr in solved:
        username = tr.cssselect('a')[0].get('href').split('/')[-1]
        score = float(tr.cssselect('td:last-child')[0].text)
        if username in usernames:
            update_solved(site_id, username, score)
            usernames.remove(username)

    # Then get other users
    for username in usernames:
        req = urllib.request.Request('https://open.kattis.com/users/%s' % username)
        req.add_header('User-Agent', user_agent)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        score = float(tree.cssselect('.rank tr:nth-child(2) td:nth-child(2)')[0].text)
        update_solved(site_id, username, score)

def scrape_poj(site_id, usernames):
    for username in usernames:
        req = urllib.request.Request('http://poj.org/userstatus?user_id=%s' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect("tr:contains('Solved:') a")[0].text
        update_solved(site_id, username, solved)

def scrape_spoj(site_id, usernames):
    for username in usernames:
        req = urllib.request.Request('http://www.spoj.com/users/%s/' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect('.profile-info-data-stats dd')[0].text
        update_solved(site_id, username, solved)

def scrape_uva(base_url, site_id, usernames):
    # uhunt has a weird API where it returns a list of bitsets of solved problems
    body_bytes = urllib.request.urlopen('%s/api/solved-bits/%s' % (base_url, ','.join(usernames))).read()
    doc = json.loads(body_bytes.decode('utf-8'))
    for obj in doc:
        count = 0
        for bs in obj['solved']:
            while bs:
                if bs & 1: count += 1
                bs >>= 1
        update_solved(site_id, obj['uid'], count)

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

for site in supported_sites:
    print('Processing %s' % site.name)
    usernames = []
    for row in db.execute("SELECT username FROM site_account WHERE site_id=?", (site.id,)):
        usernames.append(row["username"])
    site.scrape_func(site.id, usernames)

db.commit()
