import csv
import json
import os
import sys
from datetime import date, datetime, timedelta

import emails

os.chdir(os.path.dirname(os.path.abspath(__file__)))

with open ('config.json') as json_file:
    cfg=json.load(json_file)

recipients     = [ 'npartenh@butler.edu' ]  # add emails to this list for testing below
bcc_recipients = [ ]
summary_recipients = []

def get_csv_data(filename: str) -> list:
    data = []
    with open (filename) as f:
        reader=csv.DictReader(f)
        for row in reader:
            data.append(row)
    return data


def send_emails( TEST_SEND: bool = False ):
    subject = 'blogs.butler.edu Site Archival Notice'

    # imagine we ran either
    # wp db query "SELECT * FROM wp_sitemeta WHERE meta_key like '%renewed';" --path=/var/www/html
    # wp db export --tables=wp_sitemeta --path=/var/www/html --format=csv
    data = get_csv_data('sitemeta.csv')

    # send all the warnings in one email per user
    admin_data = {}

    for row in data:
        if row['meta_key'][-7:] == "renewed":
            if row['admin'] not in admin_data.keys():
                admin_data[ row['admin'] ] = { 'name': row['admin'], 'notices': [] }

        admin_data[ row['admin'] ]['notices'].append(row['slug'])

    for k,v in admin_data.items():
        if TEST_SEND:
            for recipient in recipients:
                emails.send_email(recipient, 'blogs_warning.html', subject, v)
        else:
            recipient = f"{k}@butler.edu"
            emails.send_email(recipient, 'blogs_warning.html', subject, v, bcc=bcc_recipients)


def main():
    send_emails(TEST_SEND=True)


if __name__ == '__main__':
    main()
