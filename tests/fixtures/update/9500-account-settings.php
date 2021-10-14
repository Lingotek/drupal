<?php

/**
 * @file
 * Fixture for
 *   \Drupal\Tests\lingotek\Functional\Update\LingotekUpgrade9402ClearDownloadInterimPreferenceTest.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$settings = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('name', 'lingotek.settings')
  ->execute()
  ->fetchField();
$settings = unserialize($settings);
$settings['default'] = [
  'community' => 'comm-dddd-bbbb-cccc-dddd',
  'project' => 'proj-bbbb-bbbb-cccc-dddd',
  'workflow' => 'wfwf-eeee-bbbb-cccc-dddd',
  'vault' => 'vault-cccc-bbbb-cccc-dddd',
  'filter' => 'fltr-eeee-bbbb-cccc-dddd',
  'subfilter' => 'fltr-aaaa-bbbb-cccc-dddd',
];
$connection->update('config')
  ->fields([
    'data' => serialize(array_merge_recursive($settings, [
      'account' => [
        'access_token' => 'test-9500-token',
        'login_id' => 'myloginid@drupal.org',
        'resources' => [
          'community' => [
            'comm-aaaa-bbbb-cccc-dddd' => 'Community 1',
            'comm-bbbb-bbbb-cccc-dddd' => 'Community 2',
            'comm-cccc-bbbb-cccc-dddd' => 'Community 3',
            'comm-dddd-bbbb-cccc-dddd' => 'Community 4',
            'comm-eeee-bbbb-cccc-dddd' => 'Community 5',
          ],
          'project' => [
            'proj-aaaa-bbbb-cccc-dddd' => 'Project 1',
            'proj-bbbb-bbbb-cccc-dddd' => 'Project 2',
            'proj-cccc-bbbb-cccc-dddd' => 'Project 3',
            'proj-dddd-bbbb-cccc-dddd' => 'Project 4',
            'proj-eeee-bbbb-cccc-dddd' => 'Project 5',
          ],
          'workflow' => [
            'wfwf-aaaa-bbbb-cccc-dddd' => 'Workflow 1',
            'wfwf-bbbb-bbbb-cccc-dddd' => 'Workflow 2',
            'wfwf-cccc-bbbb-cccc-dddd' => 'Workflow 3',
            'wfwf-dddd-bbbb-cccc-dddd' => 'Workflow 4',
            'wfwf-eeee-bbbb-cccc-dddd' => 'Workflow 5',
          ],
          'vault' => [
            'vault-aaaa-bbbb-cccc-dddd' => 'Vault 1',
            'vault-bbbb-bbbb-cccc-dddd' => 'Vault 2',
            'vault-cccc-bbbb-cccc-dddd' => 'Vault 3',
            'vault-dddd-bbbb-cccc-dddd' => 'Vault 4',
            'vault-eeee-bbbb-cccc-dddd' => 'Vault 5',
          ],
          'filter' => [
            'fltr-aaaa-bbbb-cccc-dddd' => 'Filter 1',
            'fltr-bbbb-bbbb-cccc-dddd' => 'Filter 2',
            'fltr-cccc-bbbb-cccc-dddd' => 'Filter 3',
            'fltr-dddd-bbbb-cccc-dddd' => 'Filter 4',
            'fltr-eeee-bbbb-cccc-dddd' => 'Filter 5',
          ],
        ],
      ],
    ],
    )),
  ])
  ->condition('name', 'lingotek.settings')
  ->execute();
