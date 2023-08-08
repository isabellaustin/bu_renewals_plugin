import csv
import json
import os
import subprocess
import sys
from datetime import date, datetime, timedelta
import mysql.connector

import emails

os.chdir(os.path.dirname(os.path.abspath(__file__)))

with open ('/var/www/html/wp-content/plugins/bu-renewals-master/email/config.json', 'r') as json_file:
    cfg=json.load(json_file)

recipients     = [ 'iaustin@butler.edu' ]  # add emails to this list for testing below
bcc_recipients = [ ]
summary_recipients = []

def get_csv_data(filename: str) -> list:
    data = []
    with open (filename) as f:
        reader=csv.DictReader(f)
        for row in reader:
            data.append(row)
    return data


def get_blogs():
    datafile = "/var/www/html/wp-content/plugins/bu-renewals-master/email/blogs.csv" 
    f = open(datafile, "w")
    
    subprocess.run(f'''wp db query "select blog_id from wp_blogs;" --path=/var/www/html''', shell=True, stdout=f)
    data = get_csv_data(datafile)

    return [int(d['blog_id']) for d in data]


def get_renewed_blogs():
    datafile = "/var/www/html/wp-content/plugins/bu-renewals-master/email/renewedblogs.csv" 
    f = open(datafile, "w")
    
    subprocess.run(f'''wp db query "select meta_key from wp_sitemeta where meta_key like '%renewed';" --path=/var/www/html ''', shell=True, stdout=f)
    data = get_csv_data(datafile)

    return [int(d['meta_key'].split("_")[1]) for d in data]


def get_recipients(blogID: int):
    datafile = "/var/www/html/wp-content/plugins/bu-renewals-master/email/recipients.csv" 
    f = open(datafile, "w")
    
    subprocess.run(f'''wp db query "select user_email from wp_users u join wp_usermeta um on u.id=um.user_id where um.meta_key='wp_{blogID}_capabilities' and um.meta_value like '%administrator%';" --path=/var/www/html''', shell=True, stdout=f)
    data = get_csv_data(datafile)

    return [d['user_email'] for d in data]


def has_date_passed(blogID: int, date_passed: bool = False ):
    p = subprocess.run(f'''wp db query "select last_updated from wp_blogs where blog_id = {blogID};" --path=/var/www/html ''', shell=True, capture_output=True)
    last_updated = p.stdout.decode()

    # if date(last_updated) > end_date:
    #     date_passed = True
    # else:
    #     date_passed = False

    return date_passed


def send_admin_emails( TEST_SEND: bool = False ):
    subject = 'blogs.butler.edu Site Archival Notice'
    
    csvfile = "/var/www/html/wp-content/plugins/bu-renewals-master/email/sitemeta.csv"
    # f = open(csvfile, "w")
    
    data = get_csv_data(csvfile)
    print(data)

    # send all the warnings in one email per user
    admin_data = {}

    for row in data:
        if row['meta_key'][-7:] == "renewed":
            if row['admin'] not in admin_data.keys():
                admin_data[ row['admin'] ] = { 'name': row['admin'], 'notices': [] }
        
        admin_data[ row['admin'] ] = { 'name': row['admin'], 'notices': [{'site': row['slug'], 'ID': row['site_id'], 'UID': row['id'],'term_date': row['meta_value']}] }

        # admin_data[ row['admin'] ]['notices'].append(row['slug'])
        # admin_data[ row['admin']['notices']]['ID'] = row['id']

        print(admin_data)

        recipients = get_recipients(row['site_id'])
    # print(recipients)

    for k,v in admin_data.items():
        if TEST_SEND:
            for recipient in recipients:
                emails.send_email(recipient, 'blogs_warning.html', subject, v)
        else:
            recipient = f"{k}@butler.edu"
            emails.send_email(recipient, 'blogs_warning.html', subject, v, bcc=bcc_recipients)

def send_archive_email( TEST_SEND: bool = False ):
    subject = 'blogs.butler.edu Site Archival Notice'

    csvfile = "/var/www/html/wp-content/plugins/bu-renewals-master/email/sitemeta.csv"
    # f = open(csvfile, "w")
    
    data = get_csv_data(csvfile)
    # print(data)

    # send all the warnings in one email per user
    admin_data = {}

    for row in data:
        if row['meta_key'][-7:] == "renewed":
            if row['admin'] not in admin_data.keys():
                admin_data[ row['admin'] ] = { 'name': row['admin'], 'notices': [] }
        
        admin_data[ row['admin'] ] = { 'name': row['admin'], 'notices': [{'site': row['slug'], 'ID': row['site_id'], 'UID': row['id'],'term_date': row['meta_value']}] }

        # admin_data[ row['admin'] ]['notices'].append(row['slug'])
        # admin_data[ row['admin']['notices']]['ID'] = row['id']

        print(admin_data)

        recipients = get_recipients(row['site_id'])
    # print(recipients)

    for k,v in admin_data.items():
        if TEST_SEND:
            for recipient in recipients:
                emails.send_email(recipient, 'archival_alert.html', subject, v)
        else:
            recipient = f"{k}@butler.edu"
            emails.send_email(recipient, 'archival_alert.html', subject, v, bcc=bcc_recipients)


def main():
    blogs = get_blogs()
    renewed = get_renewed_blogs()
    unrenewed = []

    for blog in blogs:
        if blog not in renewed:
            unrenewed.append(blog)
            # send_admin_emails(TEST_SEND=True)
        else:
            print(blog)

    for blog in unrenewed:
        date_passed = has_date_passed(blog)
        if date_passed:
            # send_archive_email(TEST_SEND=True)
            print(blog)


if __name__ == '__main__':
    main()
