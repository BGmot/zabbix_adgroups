This repository contains necessary changes to Zabbix Server v4.2.5 to implement users authentication against Active Directory (LDAP) based on their AD group membership. You can see all the diffs here so probably will be able to apply to other Zabbix Server versions too.
 
* Implemented logic
1) LDAP authentication is selected as 'Default authentication' at Administration->Authentication.
2) Zabbix Administrator creates mappings 'AD group' to 'Zabbix User Group(s)' at Administration->AD Groups. For every AD group a 'User type' is defined (User/Admin/Super Admin).
In my case I see membership information as an array of records with format 'CN=<cn_name>,OU=<ouX>,OU=<ouY>...etc'. We use only CN field to map groups.
3) If a user logs in and it does exist in internal Zabbix database (Administration->Users) then no change in behaviour - it is authenticated against LDAP server.
3) If a user logs in and does not exist in internal Zabbix dataase (Administration->Users) then:
3.1) Zabbix performs authentication against LDAP server (password verification).
3.2) Zabbix pulls the user's AD groups membership information from LDAP server.
3.3) Zabbix compares groups received in 3.2) to internal mappings created in 2) and compiles a list of internal Zabbix User Groups.
3.4) If no AD group found authentication fails.
3.5) A user is created belonging to Zabbix User Groups found in 3.3) with 'User type' defined for matched 'AD group'. If multiple AD Groups found then the highest level of 'User type' applied.

* How it is done
Two new tables introduced:
- 'adusrgrp' that stores AD groups names, IDs and respective 'User type'.
- 'adgroups_groups' that stores mappings One AD group to many (or one) internal Zabbix User Group(s).
Also lots of code additions to manage new elements of WebUI and LDAP login process.

* How to install
1) Two tables need to be added to the database so (you might wish to do full DB backup first): 
```
cd database/mysql
mysql -u <zabbix_db_user> -p -h 127.0.0.1 <name_of_zabbix_database> < ad_groups.sql
```

2) Now there are two ways to start using new php code:
2.1) Copy all these files into respective destinations of your current Zabbix server 4.2.5 installation
```
frontends/php/adusergrps.php
frontends/php/include/audit.inc.php
frontends/php/include/classes/api/API.php
frontends/php/include/classes/api/CApiServiceFactory.php
frontends/php/include/classes/api/CAudit.php
frontends/php/include/classes/api/services/CAdUserGroup.php
frontends/php/include/classes/api/services/CUser.php
frontends/php/include/classes/api/services/CUserGroup.php
frontends/php/include/classes/ldap/CLdap.php
frontends/php/include/classes/validators/CLdapAuthValidator.php
frontends/php/include/defines.inc.php
frontends/php/include/menu.inc.php
frontends/php/include/schema.inc.php
frontends/php/include/views/administration.adusergroups.edit.php
frontends/php/include/views/administration.adusergroups.list.php
```
2.2) Clone this repository into brand new folder and modify your apache configuration to use this folder for Zabbix Frontend (WebUI), i.e. change 'DocumentRoot' and all 'Directory' directives in Apache configuration files. Don't forget to reload (or restart) Apache service.

3) A good sign that you did everything right is seeing 'Ad Groups' tab under Administration in Zabbix WebUI.

* Use on your own risk. No responsibility assumed.

* Happy to receive feedback.
