*Rating* is a simple application developed as a way to demonstrate how to build an IMS LTI tool provider using the [ceLTIc LTI class library](https://github.com/celtic-project/LTI-PHP). The application allows teachers to create items which can be rated by students. A separate list of items is maintained for each link from which the tool is launched.

If the link has the Outcomes service enabled, then the associated gradebook column will be populated with the proportion of the visible items which each student has rated.

If the tool consumer offers support for the Memberships service then a list of users can be displayed on the *manage consumers* page (with a list of group sets where also supported).  For Moodle and Canvas the existing [API hooks](https://github.com/celtic-project/LTI-PHP/wiki/API-hooks) functionality in the LTI-PHP library can be used to add membership and group service support.

If the Line Item service is also supported, then an additional line item is created for each item to be rated and will be populated with the students' raw scores for the item.

The wiki area of this repository contains [documentation](https://github.com/celtic-project/Rating-PHP/wiki) for installing and using this application.
