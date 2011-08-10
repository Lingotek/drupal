
Lingotek Collaborative Translation Module

Module Version: 6.x-20110715.x

Requirements:

  1. Drupal 6.18
  2. PHP >= 5.2
  3. PECL hash >= 1.1
  4. PECL json >= 1.2.0
  5. SQL database (MySQL or PostgreSQL are supported)

  Drupal Modules Required:
    1.Locale (core)
    2.Content translation (core)

  Modules for Optional Features:
    1."Menu translation" from i18n (6.x-1.5)


Installation:

    1.Copy the unzipped module and it's folder into your drupal
      sites/all/modules folder
    2.Log in to Drupal as an administrator.
    3.Browse to Administer -> Site Building -> Modules
    4.Check Enabled next to the "Lingotek Collaborative Translation" module.
    5.Click "Save configuration"
    6.Click "Continue" to enable the required core modules.

  If you intend on using the module's "Overwrite Menus" feature, then
  follow these steps:

    1.Copy the unzipped i18n module and it's folder into your drupal
      sites/all/modules folder
    2.Log in to Drupal as an administrator.
    3.Browse to Administer -> Site Building -> Modules
    4.Check Enabled next to the "Menu translation" module.
    5.Click "Save configuration"
    6.Click "Continue" to enable the other required modules.

Configuration:

  1.  Permissions:
    1.  Log in as an administrator
    2.  Browse to Administer -> User management -> Roles and setup any roles you
      want to have different permissions for.
      (Translator, Reviewer, Project Manager, Drupal Administrator, etc)
    3.  Browse to Administer -> User management -> Permissions
    4.  Under lingotek module:
      1.  administer - Enable this for the role you wish
        to be able to edit the system-wide module settings on the module's
        administrative page (see "Administrative settings" below).
      2.  machine_translation - This allows a page creator to set which machine
        translation setting an individual page should use.  Enable this for the
        role you wish to be able to overwrite the default phase template setting
        on a page-by-page basis.  This permission also shows machine translation
        options on each target on the lingotek tab.  It will automatically sync
        when done and overwrite the drupal page, so any changes made from
        drupal to a target page will be lost.
      3.  phase_template - This allows a page creator to set which phase
        template (workflow) an individual page should use.  Enable this for the
        role you wish to be able to overwrite the default phase template setting
        on a page-by-page basis.
      4.  review_phase - This allows a user to click on the review phases,
        or tasks, for a given translation.
      5.  sync - This permission shows download options on each target
        so the user can re-download an up-to-date version of the document.
        It will overwrite the drupal page, so any changes made from
        drupal to the content will be lost.
      6.  tab - This displays the "Lingotek" tab
        on created pages and allows someone such as a project manager
        access to view the translation progress.  Additional permissions
        are required to gain access to translate or review.
      7.  translation_phase - This allows a user to click on the translation
        phases, or tasks, for a given translation.

  2.  Administrative settings:

    Authentication Settings:
      1.Log in in to Drupal as an administrator
      2.Browse to Administer -> Site configuration -> Lingotek
        (admin/settings/community_translation)
        If you are unable to find this page, make sure your role has the
        "administer community_translation" permission discussed above.
      3.Fill in your provided Login ID, Login Key, Lingotek URL, and
        Community ID.  Make sure that the Lingotek URL doesn't include any
        trailing slashes.
      4.Click "Save configuration"

    Dashboard Settings:
      1.Select a Project (or enter a Project ID if the drop-down doesn't appear)
      2.Select a Vault (or enter a Vault ID if the drop-down doesn't appear)
      3.Click "Save configuration"

    Drupal Settings:
      1.  Set the "Neutral Language" to what language Lingotek should
        consider the document when a language isn't specified.
      2.  Enable "Overwrite Menus" if you wish our module to update the
        menu navigation to point to each translation as they
        become available.  Pages that haven't finished being translated
        will point to the source document.  This requires i18n's
        Menu Translation module to be installed and enabled.
        See the above installation guide for details.
    Default Settings:
      1.  "Phase Template" is the template that will be selected as
        default when creating a new page.
      2.  "Machine Translation Engine" is the default
        setting for if the page should be published with Google translate
        or if the publishing will be deferred until after a professional
        translation.
      3.  "Available Machine Translation Options" are what options are available
        on page creation.
      7.  Click "Save configuration"


Usage:

  1.  Create a new page.  While editing the page, note the Phase Template
    setting that has been added.  The drop-down is visible if the user has
    "phase_template" permissions.
    Otherwise, this drop-down is missing and the page creator will be
    required to use the default phase template.
  2.  Publishing options are also shown, allowing you to choose weather to use
    Google translation and immediately publish the results, or if you want
    to use professional translators and defer publication until the process
    is complete.  The drop-down is visible if the user has
    "machine_translation" permissions.
    Otherwise, this drop-down is disabled and the page creator will be
    required to use the default value.
  3.  Save the page.  A new option, "Lingotek" will appear on
    the created page if your user has permissions to "tab".
  4.  Click on "Lingotek" where you can see the progress of
    the page's translations.
  5.  The title only appears once the page
    has been fully translated and reviewed (Total % = 100%) or machine
    translated or synced.
    The title gives a link to the new page with the translated content,
    as well as shows that the page has been published.  Notice that the
    title is also translated as the first segment in the
    translation process.  If "Overwrite Menus" is enabled in the module,
    then the translated title is used as the new title in the translated
    menu.  The source language, however, retains the page creator's title.
  6.  Phase shows us what step in the workflow the document is on.
    The first phase of any phase set will need "translation_phase" permissions
    to complete.  The following phases are only accessible to those with
    "review_phase" permissions.
  7.  Status is the complete percent for the entire translation process.
  8.  Published shows if the node has been marked as published to users.
