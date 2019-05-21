# Author Roles #

Author: Twisted Interactive  
Website: http://www.twisted.nl

## What does this extension do? ##

In short: this extension allows you to set permissions for each author with roles.
It is the successor of the [Author Section extension](https://github.com/kanduvisla/author_section).

More detailed: The extension allows you to set the following permissions:

- Show or hide certain sections
- Set the rights for each section to create, edit or delete entries
- **Hide fields on certain sections**
- **Let the author only see their own created entries**
- **Show or hide certain entries on the publish page of sections**
- **Hide menu items created by other extensions**

## How does it work? ##

Under the System-tab you see a new item 'Author Roles'. This is where you can manage your author roles. You can create as many roles as you want, and asign a role to multiple authors.

When you create a role you can set the permission for each section, by using the checkboxes. By clicking 'edit' underneath 'Fields' you can select which fields should be hidden for this section.

By clicking 'edit' underneath 'Entries' you can set various permissions. You can set if the author is only allowed to see the entries he/she created, and you can set a filter. The filter works quite easy actually. First you choose if the filter is to show or hide certain entries. In the textfield you can on or more ID's of entries. You can also set a range of ID's by using a hyphen.

## Important sidenote for hiding menu items created by other extensions ##

Since extensions can not (yet) influence the execution order of extensions in Symphony, you need to apply a tiny little 'hack'
to Symphony to make sure the 'Author Roles'-extension is executed as last, after all other extensions have made their possible
modifications to the navigation. To do this, you need to edit `lib/toolkit/class.extensionmanager.php` and look at the SQL-query
in the `__construct()`-function for this line:

    ORDER BY t2.delegate, t1.name

and change it to:

    ORDER BY t2.delegate, t1.id ASC

This makes the execution order of extensions ordered by ID instead of name. Now, if you added this extension as last to your list,
you're safe now, otherwise you have to go into the database, look for the `tbl_extensions`-table, write down the ID, and update the
ID to be the highest number in the table. Don't change the other extensions! After that, edit the `tbl_extensions_delegates`-table
and change the rows which have `extension_id` set to the old ID, and update them to the new ID. Now everything should work just fine!

## Sidenotes ##

- Fields that are set to 'hidden' will be made hidden with JavaScript. This is to ensure that all the data gets send and stored on a POST-action when an entry is saved. However, this could cause some issues with third-party field-types, although these aren't encountered yet.
- Fields that are required cannot be set to 'hidden', since that would cause an error when trying to store the entry.
- If you choose to filter entries (by letting an author only show his/her own entries or using the filter), pagination on the publish pages is disabled. This has to be done, because Symphony doesn't have a hook to alter the query used to retreive the entries.
