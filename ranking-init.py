#!/usr/bin/env python3
import sqlite3

db = sqlite3.connect('/home/pscadmin/psc-ranking/ranking.sqlite3')
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
               'user_id INTEGER NOT NULL, '
               'site_id INTEGER NOT NULL, '
               'solved INTEGER NOT NULL, '
               'created_date DATETIME DEFAULT CURRENT_TIMESTAMP, '
               'PRIMARY KEY(user_id, site_id))')

init_tier()
init_user()
init_site()
init_site_account()
init_site_score()

db.commit()
