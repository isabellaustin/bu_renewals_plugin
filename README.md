# bu-renewals

Manages site renewal notices for a WordPress Multisite install.

## Changelog

### 1.1 (Fall 2019; gt-renewals)
* 'My Sites' section populated with website renewal statuses.
* Non-administrators now cannot renew their websites, but are provided a list of admin accounts.
* MU dashboard widget now sorts by most recent renewal(s).
* Added field for MU-specific text prior to the renewal copy.
* https://github.com/professional-web-presence/gt-renewals/tree/master

### 1.2 (Summer 2023; bu-renewals)
* 'bu_renewals_optionspage' function in 'bu_renewals_optionspage' class becomes '__constructor'
* 'gt' globally replaced with 'bu'
* dashboard button color is changed (bu-renewals.css)
* _display_textfield replaced with _display_datepicker (bu-renewals-settings-page.php)
* added functionality for if the renewal date has passed
* added 'archived site' error checking
* implemented emailing
