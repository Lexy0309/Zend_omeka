External (module for Omeka S)
=============================

[External] is a module for [Omeka S] that display items from external libraries
like [ebsco.com] inside public frontend.

Searches are made externally, then data are cached as new items with an external
media, then Omeka S manages them like any other ones.


Installation
------------

Uncompress files and rename plugin folder `External`.

See general end user documentation for [Installing a module] and follow the
config instructions.


Usage
-----

For Ebsco, simply fill in the config form of the module with your EDS account
and password, of allow the IP of the server in your ebsco admin board.

Note: The process implies that anonymous users can create items from the fetched
data. There is no risk, because they can’t modify the data from the providers.


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

This module is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM] on GitHub)


Copyright
---------

* Copyright Daniel Berthereau, 2018


[External]: https://github.com/Daniel-KM/Omeka-S-module-External
[Omeka S]: https://omeka.org/s
[ebsco.com]: https://ebsco.com
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-External/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
