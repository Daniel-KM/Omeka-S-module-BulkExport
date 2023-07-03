Bulk Export (module for Omeka S)
================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Bulk Export] is a generic export module for [Omeka S] that provides common
output formats (json, xml, spreadsheet, text), both for admin, public and api
sides. It is easily extensible by other modules.

Just add an extension to the resource browse, resource show pages or api pages,
for example `/admin/item.ods`, `/s/fr/151.odt`, `/api/items.tsv`, or `/api-local/item/151.table.json`
(integration of the old feature from module [Api Info]).

For complex requests, numerous resources, or slow output, for example the `geojson`
that requires calls to remote https://geonames.org, you can use a specific page
in the admin board.

All outputs can be configured in admin settings and in site settings.

The list of available output can be added to resource browse view and in
resource show view via a resource page block or via an event.

Internally, it allows to manage output formats, that are responsible for
exporting metadata into a file, a stream or as a string.

You may use module [Bibliography] to add old bibliographic formats (`bibtex`,
`csl`, `ris`).


Installation
------------

This module requires the module [Log] and the optional module [Generic].

For Omeka S v3, the module [Blocks Disposition] can be used to add it in the
public sites. This is useless for Omeka S v4.

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

### Automatic list of available outputs

The list of available outputs is added automatically in the admin resource
browse pages and in the resource show pages. The list of exporters is
configurable in the settings. This is the same for the sites: use the site
settings and eventually the blocks disposition settings (for Omeka S < v4) to
display the list of  exporters. For Omeka S v4, use the resource page blocks.

### View helper

The view helper `$this->bulkExport($resourcesOrIdsOrQuery, $options)` can be
used anywhere else.

### Manual creation of export urls

The export is available directly as `/s/my-site/item/{id}.ods`, or any other
extension (`csv`, `tsv`, `ods`, `json`, `jsonld`, `geojson`, `list.txt`, `txt`,
`odt`), or the one of other modules, in particular [Bibliography]. This feature
is compatible with the module [Clean Url].

The export is available through the api endpoint too at `/api/items/{id}.ods`
(or any other extension).

It is available with the module [Api Info] too (deprecated) at `/api/infos/items/{id}.ods`.

Routes can be create manually or with the routes of the module: `site/resource-output`,
`site/resource-output-id`, `admin/resource-output`, `admin/resource-output-id`,
`api/default/output`.

### Heavy export

The limit to the number of resources to output in a single call is specified in
the settings. To output more resources or for complex or slow formats, you need
to use the bulk export process, that will create the output in a file via a
background job: just config a writer for default params, then use it and process
the export.


Notes
-----

- To convert an export with linked resource exported as url + label into linked
  resources importable, you need to apply this formula in LibreOffice Calc:
  `=REGEX($Export.B2; "(http:/api/items/)(\d+)([^|\n]*)"; "$2"; "g")`
  (to be adapted to your output).


TODO
----

- [ ] Remove Writers and factorize with Formatters.
- [ ] Select resources like in the module ebook.
- [ ] For spreadsheet, add an option (by default in admin) to set headers with the datatype and the language (so multiple headers for the same property).
- [x] Rights on exports.
- [x] Deletion of old exports.
- [ ] Make any size output real time (streamable).
- [ ] For api, allow to pass settings like in module [Api Info].
- [ ] Use request header "Accept" like .extension.


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
* Copyright Daniel Berthereau, 2019-2023 (see [Daniel-KM] on GitLab)

This module was initially inspired by the [Omeka Classic] [Export plugin], built
by [Biblibre].


[Bulk Export]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport
[Omeka S]: https://omeka.org/s
[Bibliography]: https://gitlab.com/Daniel-KM/Omeka-S-module-Bibliography
[Blocks Disposition]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlocksDisposition
[Clean Url]: https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Log]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log
[BulkExport.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/releases
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Api Info]: https://gitlab.com/Daniel-KM/Omeka-S-module/ApiInfo
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/sandywalker/webui-popover/blob/master/LICENSE.txt
[Omeka Classic]: https://omeka.org/classic
[Export plugin]: https://github.com/BibLibre/Omeka-plugin-Import
[BibLibre]: https://github.com/BibLibre
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
