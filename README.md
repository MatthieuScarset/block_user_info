Block User
==================

[![Build Status](https://travis-ci.org/MatthieuScarset/block_user_info.svg?branch=master)](https://travis-ci.org/MatthieuScarset/block_user_info)

This module provides you with a custom block to display a user's information
with entity view display.


Motivation
----------

Have you ever needed to display the current user in your sidebar or header?
Drupal don't come with a default block for this.

This is why we created the Block User module.

It enables you to render a user view mode, wherever you want on your site.


Usage
-----
[![Video demonstration](https://goo.gl/B7dDob)](https://youtu.be/RFyGzfkf1jM)

1. Install the module.

2. Create a new User display mode (Admin > Settings > Accounts) (*optional*)

3. Create a new Block (Admin > Block Layout).

3. Select a display mode

4. Select which user to display:
  * The current logged in user
  * The current node's author
  * A list of specific user(s) (selected by username)

5. Select a specific user to display (*optional*).

6. Add a custom CSS class (*optional*)

7. Save the block.


Notes
-----

Need something this module doesn't do yet?

[Open a new issue](https://goo.gl/WoSxAi) and we'll try fix it ASAP :)


Todo
----

- Add the possibility **to select users by roles**.
