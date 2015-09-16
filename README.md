# TestOverview

The main goal is to make accumulating test results from multiple tests in arbitrary locations a lot more convenient. These test should be configurable in the test overview object; RBAC should be applied in the usual way, ie. a lecturer can only select his own tests/tests he or she has access to for inclusion in the overview. (The test overview should not be "hierarchical" object that has to "contain" the test it accumulates like a folder or category.)

The overview itself should present a table matrix of users (rows), test (end) results (percentages; columns) and a final mean value column. The matrix fields should have different background colors for passed (green), not passed (red) and perhaps not finished (yellow) retaining white for no results (yet).

## Installation Instructions
1. Clone this repository to <ILIAS_DIRECTORY>/Customizing/global/plugins/Services/Repository/RepositoryObject/TestOverview
2. Login to ILIAS with an administrator account (e.g. root)
3. Select **Plugins** from the **Administration** main menu drop down.
4. Search the **TestOverview** plugin in the list of plugin and choose **Activate** from the **Actions** drop down.

### More Information
[Ilias Feature Wiki Entry](http://www.ilias.de/docu/goto_docu_wiki_1357_Test_Overview.html)