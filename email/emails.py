import json
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

from jinja2 import Environment, FileSystemLoader, select_autoescape

with open ('config.json') as json_file:
    cfg=json.load(json_file)

env = Environment(
    loader=FileSystemLoader('email_templates'),
    autoescape=select_autoescape(['html', 'xml'])
)


def send_email(recipient: str = '', template: str = 'blogs_warning.html', subject: str = "", data: dict = {}, bcc: list[str] = [] ) -> bool:
    if recipient == '':
        return False

    smtp_server = cfg['email']['host']
    port = cfg['email']['port']
    sender = cfg['email']['user']
    password = cfg['email']['password']

    message = MIMEMultipart("alternative")
    message["Subject"] = subject
    message["From"]    = sender
    message["To"]      = recipient

    if len(bcc) > 0:
        recipient = [recipient] + bcc

    template = env.get_template(template)
    message_html = template.render(data=data)

    message_part = MIMEText(message_html, "html")
    message.attach(message_part)

    with smtplib.SMTP(smtp_server, port) as server:
        server.starttls()
        server.login(sender, password)
        server.sendmail( sender, recipient, message.as_string() )


    return True


