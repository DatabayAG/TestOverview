# TestOverview

The main goal is to make accumulating test results from multiple tests in arbitrary locations a lot more convenient. These test should be configurable in the test overview object; RBAC should be applied in the usual way, ie. a lecturer can only select his own tests/tests he or she has access to for inclusion in the overview. (The test overview should not be "hierarchical" object that has to "contain" the test it accumulates like a folder or category.)

The overview itself should present a table matrix of users (rows), test (end) results (percentages; columns) and a final mean value column. The matrix fields should have different background colors for passed (green), not passed (red) and perhaps not finished (yellow) retaining white for no results (yet).

**Table of Contents**

* [Requirements](#requirements)
* [Installation](#installation)
* [Configuration](#configuration)
* [Specifications](#specifications)
* [Other information](#other-information)
    * [Correlations](#correlations)
    * [Bugs](#bugs)

## Requirements

* PHP: [![Minimum PHP Version](https://img.shields.io/badge/Minimum_PHP-7.2.x-blue.svg)](https://php.net/) [![Maximum PHP Version](https://img.shields.io/badge/Maximum_PHP-7.4.x-blue.svg)](https://php.net/)
* ILIAS: [![Minimum ILIAS Version](https://img.shields.io/badge/Minimum_ILIAS-6.x-orange.svg)](https://ilias.de/) [![Maximum ILIAS Version](https://img.shields.io/badge/Maximum_ILIAS-7.x-orange.svg)](https://ilias.de/)

## Installation
1. Clone this repository to <ILIAS_DIRECTORY>/Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview
2. Login to ILIAS with an administrator account (e.g. root)
3. Select **Plugins** from the **Administration** main menu drop down.
4. Search the **TestOverview** plugin in the list of plugin and choose **Install** from the **Actions** drop down then **Activate**.

## Configuration

This plugin adds a new Object "Test Overview".

1. Add a new Test Overview to an existing Course.
2. Edit the newly created Test Overview Object.
3. Add (existing) Tests to the Test Overview.

## Specifications

None

## Other information


[Ilias Feature Wiki Entry](http://www.ilias.de/docu/goto_docu_wiki_1357_Test_Overview.html)

#### Correlations

Uses Course and Test Objects.

#### Bugs

None known
