#!/usr/bin/env python3
'''
Creates database tables and populates them with seed data.
'''
import collections, os, sqlite3

# This assumes that ranking.sqlite3 is in the same folder as this script.
dir_path = os.path.dirname(os.path.realpath(__file__))
db_path = os.path.join(dir_path + '/ranking.sqlite3')

# Connect to database.
db = sqlite3.connect(db_path)
db.row_factory = sqlite3.Row

def init_tier():
    db.execute('CREATE TABLE IF NOT EXISTS tier('
               'id INTEGER PRIMARY KEY, '
               'name TEXT NOT NULL, '
               'minimum_score INTEGER NOT NULL, '
               'created_date DATETIME DEFAULT CURRENT_TIMESTAMP)')

def init_user():
    db.execute('CREATE TABLE IF NOT EXISTS user('
               'id INTEGER PRIMARY KEY, '
               'first_name TEXT NOT NULL, '
               'last_name TEXT NOT NULL, '
               'created_date DATETIME DEFAULT CURRENT_TIMESTAMP)')

def init_site():
    db.execute('CREATE TABLE IF NOT EXISTS site('
               'id INTEGER PRIMARY KEY, '
               'name TEXT NOT NULL, '
               'profile_url TEXT NOT NULL, '
               'created_date DATETIME DEFAULT CURRENT_TIMESTAMP)')

def init_site_account():
    db.execute('CREATE TABLE IF NOT EXISTS site_account('
               'user_id INTEGER NOT NULL, '
               'site_id INTEGER NOT NULL, '
               'username TEXT NOT NULL, '
               'created_date DATETIME DEFAULT CURRENT_TIMESTAMP, '
               'PRIMARY KEY(user_id, site_id))')

def init_site_score():
    db.execute('CREATE TABLE IF NOT EXISTS site_score('
               'id INTEGER PRIMARY KEY, '
               'user_id INTEGER NOT NULL, '
               'site_id INTEGER NOT NULL, '
               'solved INTEGER NOT NULL, '
               'created_date DATETIME DEFAULT CURRENT_TIMESTAMP)')

def seed_tier():
    Tier = collections.namedtuple('Tier', 'id name minimum_score')
    tiers = [
                Tier(id=1, name='Bronze', minimum_score=0),
                Tier(id=2, name='Silver', minimum_score=5),
                Tier(id=3, name='Gold', minimum_score=15),
                Tier(id=4, name='Platinum', minimum_score=30),
                Tier(id=5, name='Diamond', minimum_score=40)
            ]

    for tier in tiers:
        db.execute('INSERT INTO tier (id, name, minimum_score) VALUES (?,?,?)',
                (tier.id, tier.name, tier.minimum_score))

def seed_site():
    Site = collections.namedtuple('Site', 'id name profile_url')
    sites = [
            Site(id=1, name='Caribbean Online Judge',
                profile_url='http://coj.uci.cu/user/useraccount.xhtml?username=%s'),
            Site(id=2, name='CodeChef', 
                profile_url='https://www.codechef.com/users/%s'),
            Site(id=3, name='Codeforces',
                profile_url='http://www.codeforces.com/api/user.status?handle=%s'),
            Site(id=4, name='ICPC Live Archive',
                profile_url='https://open.kattis.com/users/%s'),
            Site(id=5, name='Kattis',
                profile_url='https://open.kattis.com/users/%s'),
            Site(id=6, name='Peking Online Judge',
                profile_url='http://poj.org/userstatus?user_id=%s'),
            Site(id=7, name='Sphere Online Judge',
                profile_url='http://www.spoj.com/users/%s/'),
            Site(id=8, name='UVa Online Judge', 
                profile_url='https://icpcarchive.ecs.baylor.edu/uhunt/api/solved-bits/%s'),
            ]

    for site in sites:
        db.execute('INSERT INTO site (id, name, profile_url) VALUES (?,?,?)',
                (site.id, site.name, site.profile_url))

# Create tables if they don't exist.
init_tier()
init_user()
init_site()
init_site_account()
init_site_score()

# Seed the tables with initial data.
seed_tier()
seed_site()

# Commit database transactions.
db.commit()
