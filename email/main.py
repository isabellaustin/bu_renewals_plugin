import csv
import json
import os
import subprocess
from datetime import date, datetime, timedelta
import mysql.connector
from phpserialize import *
from datetime import datetime, timedelta
import pandas as pd

os.chdir(os.path.dirname(os.path.abspath(__file__)))

with open ('/var/www/html/wp-content/plugins/bu-renewals-master/email/config.json', 'r') as json_file:
    cfg=json.load(json_file)

# recipients           = [ ]  # add emails to this list for testing below
bcc_recipients       = [ ]
summary_recipients   = [ ]

def get_csv_data(filename: str) -> list:
    data = []
    with open (filename) as f:
        reader=csv.DictReader(f)
        for row in reader:
            data.append(row)
    return data


def get_all_blogs() -> list[int]:
    all_blogs = []

    header = ['blog_id','path','registered'] 
    with open('/var/www/html/wp-content/plugins/bu-renewals-master/email/csv_files/blogs.csv', 'w', encoding='UTF8') as f:
        writer = csv.writer(f)
        writer.writerow(header)

        cursor = cnx.cursor()
        query = ("select blog_id,path,registered from wp_blogs")
        cursor.execute(query)

        results = cursor.fetchall()
        for r in results:
            blog_id = int(r[0])
            path = r[1]
            registered = r[2]
            all_blogs.append(blog_id)

            data = [f'{blog_id}', f'{path}', f'{registered}']
            writer.writerow(data)

    cursor.close()
    return all_blogs


def get_recipients(blogID: int) -> list[str]:
    cursor = cnx.cursor()
    query = (f'''select user_email from wp_users u 
                join wp_usermeta um on u.id=um.user_id 
                where um.meta_key="wp_{blogID}_capabilities" 
                and um.meta_value like "%administrator%"''')
    cursor.execute(query)

    results = cursor.fetchall()
    recipients = []
    for r in results:

        user_email = r[0]
        recipients.append(user_email)

    cursor.close()
    return recipients


def get_renewed_blogs() -> list[str]:
    datafile = "/var/www/html/wp-content/plugins/bu-renewals-master/email/csv_files/renewedblogs.csv" 
    f = open(datafile, "w")

    subprocess.run(f'''wp db query "select meta_key from wp_sitemeta where meta_key like '%renewed';" --path=/var/www/html ''', shell=True, stdout=f)
    data = get_csv_data(datafile)

    return [int(d['meta_key'].split("_")[1]) for d in data]


def sitemeta_csv_renewed() -> None:
    header = ['id','site_id','meta_key','meta_value','admin','slug'] 
    with open('/var/www/html/wp-content/plugins/bu-renewals-master/email/csv_files/sitemeta-renewed.csv', 'w', encoding='UTF8') as f:
        writer = csv.writer(f)
        writer.writerow(header)

        cursor = cnx.cursor()
        query = ("select * from wp_sitemeta where meta_key like '%renewed'")
        cursor.execute(query)

        results = cursor.fetchall()
        for r in results:
            meta_id = int(r[0])
            site_id = int(r[1]) # always will be 1
            meta_key = r[2]

            meta_value = r[3]
            blog_id = int(meta_value.split("|")[0])
          
            meta_value_date = meta_value.split("|")[1]
            blog_renewed_date = (datetime.strptime(meta_value_date, '%Y-%m-%d %H:%M:%S')).date()

            blog_admin = meta_value.split("|")[2]

            p = subprocess.run(f'''wp db query "select path from wp_blogs where blog_id = {blog_id};" --path=/var/www/html''', shell=True, capture_output=True)
            slug = str(p.stdout.decode().split("\n")[1])

            data = [f'{meta_id}', f'{blog_id}', f'{meta_key}', f'{blog_renewed_date}', f'{blog_admin}', f'{slug}']
            writer.writerow(data)

        cursor.close()


def sitemeta_csv_unrenewed() -> None:
    header = ['id','site_id','slug', 'admin'] 
    with open('/var/www/html/wp-content/plugins/bu-renewals-master/email/csv_files/sitemeta-unrenewed.csv', 'w', encoding='UTF8') as f:
        writer = csv.writer(f)
        writer.writerow(header)

        cursor = cnx.cursor()
        query = ("select site_id,blog_id,path from wp_blogs")
        cursor.execute(query)

        results = cursor.fetchall()
        for r in results:
            blog_id = int(r[1])
            path = r[2]
            site_id = int(r[0])

            admins = get_recipients(blog_id)
            recipients = '|'.join(admins)

            data = [f'{site_id}', f'{blog_id}', f'{path}', f'{recipients}']
            writer.writerow(data)

        cursor.close()


def get_end_date() -> str:
    cursor = cnx.cursor()
    query = ('select meta_value from wp_sitemeta where meta_key like "%bu%" and meta_value like "%end_date%"')
    cursor.execute(query)

    results = cursor.fetchall()
    for r in results:
        r = r[0]
        data = loads(r.encode())
        end_date = data[list(data.keys())[1]].decode() # end_date is (and should always be) the second key in this dict

    cursor.close()
    return end_date


def has_date_passed() -> bool:
    today = date.today()

    db_date = get_end_date()
    end_date = (datetime.strptime(db_date, '%Y-%m-%d')).date()

    if( today > end_date ):
        date_passed = True
    else:
        date_passed = False

    return date_passed


def send_renewal_email( renewed, countdown: str, TEST_SEND: bool = False ): #csv_type:str,
    subject = 'blogs.butler.edu Site Archival Notice'  

    csvfile = f"/var/www/html/wp-content/plugins/bu-renewals-master/email/csv_files/sitemeta-unrenewed.csv"
    df = pd.read_csv(csvfile)       # remove renewed sites
    for blog_id in renewed:
        df = df.drop(df[df.site_id == blog_id].index)
        df.to_csv(csvfile, index=False)
    data = get_csv_data(csvfile)    # load data

    # send all the warnings in one email per user
    admin_data = {}
    db_date = get_end_date()
    end_date = str((datetime.strptime(db_date, '%Y-%m-%d')).date())

    for row in data:
        for user in row['admin'].split("|"):
            if user not in admin_data.keys():
                admin_data[ user ] = { 'email': user, 'notices': [] }
            admin_data[ user ]['notices'].append({'blog_id': row['site_id'], 'site': row['slug'], 'renewal_due_date': end_date, 'archival_countdown': countdown})

    for k,v in admin_data.items():
        recipient = v['email']

        if TEST_SEND:
            # for recipients in admin_list:
            print(recipient)
            # emails.send_email(recipient, 'blogs_warning.html', subject, v)
        else:
            recipient = f"{k}@butler.edu"
            # emails.send_email(recipient, 'blogs_warning.html', subject, v, bcc=bcc_recipients)


def send_archive_email( TEST_SEND: bool = False ):
    subject = 'blogs.butler.edu Site Archival Notice'
    
    csvfile = f"/var/www/html/wp-content/plugins/bu-renewals-master/email/csv_files/sitemeta-unrenewed.csv" 
        # will this sql query still work after blogs have been archived?
        # select * from wp_blogs where archived = 1;
    data = get_csv_data(csvfile)

    # send all the warnings in one email per user
    admin_data = {}
    db_date = get_end_date()
    end_date = str((datetime.strptime(db_date, '%Y-%m-%d')).date())

    # if csv_type == "unrenewed":
    for row in data:
        for user in row['admin'].split("|"):
            if user not in admin_data.keys():
                admin_data[ user ] = { 'email': user, 'notices': [] }
            admin_data[ user ]['notices'].append({'blog_id': row['site_id'], 'site': row['slug'], 'renewal_due_date': end_date})
        # print(admin_data[ user ])


    for k,v in admin_data.items():
        recipient = v['email']

        if TEST_SEND:
            # for recipients in admin_list:
            print(recipient)
            # emails.send_email(recipient, 'archival_alert.html', subject, v)
        else:
            recipient = f"{k}@butler.edu"
            # emails.send_email(recipient, 'archival_alert.html', subject, v, bcc=bcc_recipients)


def main(): 
 # renewal email timeline variables:
    # today
    today = date.today()
    # renewal deadline
    renewal_deadline = (datetime.strptime(get_end_date(), '%Y-%m-%d')).date()
    # 30 days out
    thirty_days = renewal_deadline - timedelta(days=30)
    # 7 days out
    seven_days = renewal_deadline - timedelta(days=7)
    # 3 days out
    three_days = renewal_deadline - timedelta(days=3)
    # 1 day out
    one_day = renewal_deadline - timedelta(days=1)

    renewed = get_renewed_blogs()
    sitemeta_csv_unrenewed()
    date_passed = has_date_passed() #checks if renewal deadline has passed
    if date_passed:
        send_archive_email(TEST_SEND=True)
        print("archive")
    else:
        countdown = '30+'
        send_renewal_email(renewed,countdown,TEST_SEND=True)
        print("renew")
    print(date_passed)

'''
    renewed = get_renewed_blogs()
    sitemeta_csv_unrenewed()
    date_passed = has_date_passed() #checks if renewal deadline has passed
    if date_passed:
        send_archive_email(TEST_SEND=True)
    elif today == thirty_days:
        countdown = '30'
        send_renewal_email(renewed,countdown,TEST_SEND=True)
    elif today == seven_days:
        countdown = '7'
        send_renewal_email(renewed,countdown,TEST_SEND=True)
    elif today == three_days:
        countdown = '3'
        send_renewal_email(renewed,countdown,TEST_SEND=True)
    elif today == one_day:
        countdown = '1'
        send_renewal_email(renewed,countdown,TEST_SEND=True)
'''


if __name__ == '__main__':
    cnx = mysql.connector.connect(user=cfg["db_username"], password=cfg["db_password"], host="docker-dev.butler.edu", database="wp_blogs_dev")
    main()
