ezComponents' Workflow
=====================

This library is a git-accessible version of the [Workflow component from ezComponents](http://ezcomponents.org/docs/tutorials).

Disclaimer
----

This repository is not in active development directly so I cannot accept pull requests. Any bugs and so on should be reported [ezComponents](http://ezcomponents.org). I am not affiliated with ezComponents in any way and take no credit for their work.

Documentation
-------------

Official documentation can be found at:

- Tutorials: http://ezcomponents.org/docs/tutorials/Workflow
- API: http://ezcomponents.org/docs/api

Requirements
------------

The library required PHP 5.2.1. [Composer](http://getcomposer.org) is suggested but not required.

Installation
------------

Only instructions for inclusion of this library using composer are provided. Of course you can create a sub-module or fork/clone the library if you wish but you'll have to search for instructions if you're not sure how to do it :).

```javascript
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/beldougie/ezcomponents-workflow.git"
        }
    ],
    "require": {
        "belsym\ezcomponents-workflow": "1.4.*@dev"
    }
}
```

run `composer.phar update` and the library should update.

**NOTE** the `@dev` in the version is necessary. See [here](https://groups.google.com/forum/#!topic/composer-dev/_g3ASeIFlrc/discussion) for more details.


The library will be installed under your default vendors directory under `belsym/ezComponents/Workflow`

