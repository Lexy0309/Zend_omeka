Guest User (module for Omeka S)
===============================

[![Build Status](https://travis-ci.org/biblibre/omeka-s-module-GuestUser.svg?branch=master)](https://travis-ci.org/biblibre/omeka-s-module-GuestUser)

[Guest User] is a module for [Omeka S] that creates a role called `guest`, and
provides configuration options for a login and registration screen. Guest users
become registered users in Omeka S, but have no other privileges to the admin
side of your Omeka S installation. This module is thus intended to be a common
module that other modules needing a guest user use as a dependency.

This module is a full rewrite of the [plugin Guest User] for [Omeka Classic].


Installation
------------

Uncompress files in the module directory and rename module folder `GuestUser`.


Usage
-----

### Guest user login form

A guest user login form is provided in `/s/my_site/guest-user/login`.

### Main login form

In some cases, you may want to use the same login form for all users, so you may
have to adapt it.

```
    <?php
    if ($this->identity()):
        echo $this->hyperlink($this->translate('Logout'), $this->url()->fromRoute('site/guest-user', ['site-slug' => $site->slug(), 'action' => 'logout']), ['class' => 'logout']);
    else:
        echo $this->hyperlink($this->translate('Login'), $this->url()->fromRoute('site/guest-user', ['site-slug' => $site->slug(), 'action' => 'login']), ['class' => 'login']);
    endif;
    ?>
```

### Omeka S user bar

The Omeka S user bar is adapted to the module. You may have to adapt your theme.

### Terms agreement

A check box allows to force guest users to accept terms agreement.

A button in the config forms allows to set or unset all guest users acceptation,
in order to allow update of terms.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Contact
-------

Current maintainers:

* Biblibre
* Daniel Berthereau (see [Daniel-KM] on GitHub)


Copyright
---------

* Copyright Biblibre, 2016-2017
* Copyright Daniel Berthereau, 2017-2018


[Guest User]: https://github.com/biblibre/Omeka-S-module-GuestUser
[plugin Guest User]: https://github.com/omeka/plugin-GuestUser
[Omeka S]: https://www.omeka.org/s
[Omeka Classic]: https://omeka.org
[module issues]: https://github.com/biblibre/Omeka-S-module-GuestUser/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
