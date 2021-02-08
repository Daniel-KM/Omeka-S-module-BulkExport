Bulk Export (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Bulk Export] is a generic export module for [Omeka S] that provides common
output formats (json, xml, spreadsheet, text), both for admin and public sides.
It is easily extensible by other modules.

Other known modules that provides output: [Bibliography].

Internally, it allows to manage output formats, that are responsible for
exporting metadata into a file, a stream or as a string.

As an example, this module defines a sample writer that exports all resources as
a spreadsheet, that can be imported automatically by [Bulk Import].


Installation
------------

This module requires the module [Log] and the optional module [Generic].

**Important**: If you use the module [CSVImport] in parallel, you should apply
[this patch] or use [this version].

See general end user documentation for [installing a module].

* From the zip

Download the last release [BulkExport.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `BulkExport`, go to the root of the module, and run:

```sh
composer install --no-dev
```


Quick start
-----------

First, choose a writer and config it.

Finally, process the export.

To create a new writer, take your inspiration on the existing `SpreadsheetWriter`.

The output can be set for admin board in the main settings and for each site in
the settings of each site.

The export is available directly as `/s/my-site/item/{id}.ods`, or any other
extension (tsv, csv, json, json-ld, list.txt, txt, odt, ods), or the one of other
modules, in particular [Bibliography]. This feature is compatible with the module
[Clean Url].

The export is available through the api endpoint too with the module [Api Info]
at `/api/infos/item/{id}.ods`, or any other extension.


Notes
-----

- To convert an export with linked resource exported as url + label into linked
  resources importable, you need to apply this formula in LibreOffice Calc:
  `=REGEX($Export.B2; "(http:/api/items/)(\d+)([^|\n]*)"; "$2"; "g")`
  (to be adapted to your output).


TODO
----

- [ ] Select resources like in the module ebook.
- [ ] For spreadsheet, add an option (by default in admin) to set headers with the datatype and the language (so multiple headers for the same property).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
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


Copyright
---------

* Copyright BibLibre, 2016-2017
* Copyright Daniel Berthereau, 2019-2021 (see [Daniel-KM] on GitLab)

This module was initially inspired by the [Omeka Classic] [Export plugin], built
by [Biblibre].


[Bulk Export]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport
[Omeka S]: https://omeka.org/s
[Bulk Import]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkImport
[Omeka Classic]: https://omeka.org/classic
[Export plugin]: https://github.com/BibLibre/Omeka-plugin-Import
[Bibliography]: https://gitlab.com/Daniel-KM/Omeka-S-module-Bibliography
[Clean Url]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Log]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log
[BulkExport.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/releases
[installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[CSV Import]: https://github.com/omeka-s-modules/CSVImport
[Api Info]: https://gitlab.com/Daniel-KM/Omeka-S-module/ApiInfo
[this patch]: https://github.com/omeka-s-modules/CSVImport/pull/182
[this version]: https://github.com/Daniel-KM/Omeka-S-module-CSVImport
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/sandywalker/webui-popover/blob/master/LICENSE.txt
[BibLibre]: https://github.com/BibLibre
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
