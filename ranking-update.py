#!/usr/bin/python3
import collections, functools, json, lxml.etree, lxml.html, sqlite3, urllib.request

db = sqlite3.connect('/home/pscadmin/psc-ranking/ranking.sqlite3')
db.row_factory = sqlite3.Row

def update_solved(site, username, solved):
    db.execute("UPDATE user_site SET solved=?, updated_time=strftime('%s','now') WHERE username=? AND site=?", (solved, username, site))

def scrape_codeforces(usernames):
    # I don't see a better way than scanning all submissions of the user
    for username in usernames:
        body_bytes = urllib.request.urlopen('http://www.codeforces.com/api/user.status?handle=%s' % username).read()
        doc = json.loads(body_bytes.decode('utf-8'))
        solved = set()
        for obj in doc['result']:
            # I'm assuming that (contestId, index) is a unique identifier for the problem
            if obj['verdict'] == 'OK': solved.add((obj['problem']['contestId'], obj['problem']['index']))
        update_solved('codeforces', username, len(solved))

def scrape_codechef(usernames):
    for username in usernames:
        req = urllib.request.Request('https://www.codechef.com/users/%s' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect("#problem_stats tr:nth-child(2) td")[0].text
        update_solved('codechef', username, solved)

def scrape_coj(usernames):
    for username in usernames:
        req = urllib.request.Request('http://coj.uci.cu/user/useraccount.xhtml?username=%s' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect("div.panel-heading:contains('Solved problems') span.badge")[0].text
        update_solved('coj', username, solved)

def scrape_kattis(usernames):
    # We'll only get users who are listed as University of Calgary
    req = urllib.request.Request('https://open.kattis.com/universities/ucalgary.ca')
    # Kattis seems to block urllib user agent
    req.add_header('User-Agent', 'Mozilla/5.0 (X11; CrOS x86_64 8350.68.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36')
    tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
    solved = tree.cssselect('.table-kattis tbody tr')
    for tr in solved:
        username = tr.cssselect('a')[0].get('href').split('/')[-1]
        solved = float(tr.cssselect('td:last-child')[0].text)
        if username in usernames:
            update_solved('kattis', username, solved)

def scrape_poj(usernames):
    for username in usernames:
        req = urllib.request.Request('http://poj.org/userstatus?user_id=%s' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect("tr:contains('Solved:') a")[0].text
        update_solved('poj', username, solved)

def scrape_spoj(usernames):
    for username in usernames:
        req = urllib.request.Request('http://www.spoj.com/users/%s/' % username)
        tree = lxml.html.fromstring(urllib.request.urlopen(req).read())
        solved = tree.cssselect('.profile-info-data-stats dd')[0].text
        update_solved('spoj', username, solved)

def scrape_uva(site, base_url, usernames):
    # uhunt has a weird API where it returns a list of bitsets of solved problems
    body_bytes = urllib.request.urlopen('%s/api/solved-bits/%s' % (base_url, ','.join(usernames))).read()
    doc = json.loads(body_bytes.decode('utf-8'))
    for obj in doc:
        count = 0
        for bs in obj['solved']:
            while bs:
                if bs & 1: count += 1
                bs >>= 1
        update_solved(site, obj['uid'], count)

SupportedSite = collections.namedtuple('SupportedSite', 'id name scrape_func')
supported_sites = [
    SupportedSite(id='coj', name='Caribbean Online Judge', scrape_func=scrape_coj),
    SupportedSite(id='codechef', name='CodeChef', scrape_func=scrape_codechef),
    SupportedSite(id='codeforces', name='Codeforces', scrape_func=scrape_codeforces),
    SupportedSite(id='icpcarchive', name='ICPC Live Archive', scrape_func=functools.partial(scrape_uva, 'icpcarchive', 'https://icpcarchive.ecs.baylor.edu/uhunt')),
    SupportedSite(id='kattis', name='Kattis', scrape_func=scrape_kattis),
    SupportedSite(id='poj', name='Peking Online Judge', scrape_func=scrape_poj),
    SupportedSite(id='spoj', name='Sphere Online Judge', scrape_func=scrape_spoj),
    SupportedSite(id='uva', name='UVa Online Judge', scrape_func=functools.partial(scrape_uva, 'uva', 'http://uhunt.felix-halim.net')),
]

for site in supported_sites:
    print('Processing %s' % site.name)
    usernames = []
    for row in db.execute("SELECT username FROM user_site WHERE site=?", (site.id,)):
        usernames.append(row["username"])
    site.scrape_func(usernames)

db.commit()
