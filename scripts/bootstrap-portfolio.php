<?php

/**
 * Bootstrap portfolio content model on the existing lab site.
 * Run: ddev drush php:script scripts/bootstrap-portfolio.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;

$messenger = static function (string $msg): void {
  echo $msg . PHP_EOL;
};

// --- Vocabulary: medium ---
if (!Vocabulary::load('medium')) {
  Vocabulary::create([
    'vid' => 'medium',
    'name' => 'Medium',
    'description' => 'print / digital / licensed',
  ])->save();
  $messenger('Created vocabulary medium');
}
foreach (['print', 'digital', 'licensed'] as $name) {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'medium', 'name' => $name]);
  if (!$existing) {
    Term::create(['vid' => 'medium', 'name' => $name])->save();
    $messenger("Created term: $name");
  }
}

// --- Content type: case_study ---
if (!NodeType::load('case_study')) {
  $type = NodeType::create([
    'type' => 'case_study',
    'name' => 'Case Study',
    'description' => 'Portfolio case study',
    'new_revision' => TRUE,
    'preview_mode' => DRUPAL_OPTIONAL,
    'display_submitted' => FALSE,
  ]);
  $type->save();
  node_add_body_field($type, 'Process');
  $messenger('Created content type case_study');
}

// Field helper.
$ensure_storage = static function (string $field_name, string $type, array $settings = [], int $cardinality = 1) use ($messenger): void {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $settings,
    ])->save();
    $messenger("Storage $field_name");
  }
};

$ensure_field = static function (string $field_name, string $label, array $settings = [], array $instance = []) use ($messenger): void {
  if (!FieldConfig::loadByName('node', 'case_study', $field_name)) {
    FieldConfig::create($instance + [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'case_study',
      'label' => $label,
      'settings' => $settings,
      'required' => FALSE,
    ])->save();
    $messenger("Field $field_name");
  }
};

// Plain text fields.
foreach ([
  'field_role' => 'Role',
  'field_year_era' => 'Year / era',
  'field_constraints' => 'Constraints',
  'field_outcome' => 'Outcome',
] as $name => $label) {
  $ensure_storage($name, 'string', ['max_length' => 255]);
  // Longer text for constraints/outcome.
  if (in_array($name, ['field_constraints', 'field_outcome'], TRUE)) {
    // Replace string storage if we want long text — recreate as string_long.
  }
}

// Use string_long for constraints and outcome.
foreach (['field_constraints', 'field_outcome'] as $name) {
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage && $storage->getType() === 'string') {
    // Leave as string for simplicity if already created; recreate properly.
  }
}

// Recreate long fields properly if short ones were just made empty.
foreach (['field_constraints' => 'Constraints', 'field_outcome' => 'Outcome'] as $name => $label) {
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage && $storage->getType() === 'string') {
    if ($fc = FieldConfig::loadByName('node', 'case_study', $name)) {
      $fc->delete();
    }
    $storage->delete();
  }
  $ensure_storage($name, 'string_long');
  $ensure_field($name, $label);
}

foreach (['field_role' => 'Role', 'field_year_era' => 'Year / era'] as $name => $label) {
  $ensure_storage($name, 'string', ['max_length' => 255]);
  $ensure_field($name, $label);
}

// Historical vs speculative list.
$ensure_storage('field_provenance', 'list_string', [
  'allowed_values' => [
    'historical' => 'Historical',
    'speculative' => 'Speculative',
  ],
]);
$ensure_field('field_provenance', 'Provenance', [], [
  'default_value' => [['value' => 'historical']],
  'required' => TRUE,
]);

// Medium taxonomy reference.
$ensure_storage('field_medium', 'entity_reference', [
  'target_type' => 'taxonomy_term',
], FieldStorageConfig::CARDINALITY_UNLIMITED);
$ensure_field('field_medium', 'Medium', [
  'handler' => 'default:taxonomy_term',
  'handler_settings' => [
    'target_bundles' => ['medium' => 'medium'],
    'auto_create' => FALSE,
  ],
]);

// Cover image (media reference).
$ensure_storage('field_cover_image', 'entity_reference', [
  'target_type' => 'media',
]);
$ensure_field('field_cover_image', 'Cover image', [
  'handler' => 'default:media',
  'handler_settings' => [
    'target_bundles' => ['image' => 'image'],
  ],
]);

// Gallery.
$ensure_storage('field_gallery', 'entity_reference', [
  'target_type' => 'media',
], FieldStorageConfig::CARDINALITY_UNLIMITED);
$ensure_field('field_gallery', 'Gallery', [
  'handler' => 'default:media',
  'handler_settings' => [
    'target_bundles' => ['image' => 'image'],
  ],
]);

// Form / view displays.
$form = EntityFormDisplay::load('node.case_study.default')
  ?: EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'case_study',
    'mode' => 'default',
    'status' => TRUE,
  ]);
$view = EntityViewDisplay::load('node.case_study.default')
  ?: EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'case_study',
    'mode' => 'default',
    'status' => TRUE,
  ]);
$teaser = EntityViewDisplay::load('node.case_study.teaser')
  ?: EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'case_study',
    'mode' => 'teaser',
    'status' => TRUE,
  ]);

$weights = [
  'title' => -5,
  'field_cover_image' => 0,
  'field_role' => 1,
  'field_year_era' => 2,
  'field_provenance' => 3,
  'field_medium' => 4,
  'field_constraints' => 5,
  'body' => 6,
  'field_outcome' => 7,
  'field_gallery' => 8,
  'status' => 100,
  'uid' => 101,
  'created' => 102,
  'path' => 103,
];

foreach ($weights as $component => $weight) {
  if ($component === 'title') {
    continue;
  }
  $widget = match ($component) {
    'body' => ['type' => 'text_textarea_with_summary', 'weight' => $weight],
    'field_cover_image', 'field_gallery' => ['type' => 'media_library_widget', 'weight' => $weight],
    'field_medium' => ['type' => 'options_buttons', 'weight' => $weight],
    'field_provenance' => ['type' => 'options_select', 'weight' => $weight],
    'field_constraints', 'field_outcome' => ['type' => 'string_textarea', 'weight' => $weight],
    'field_role', 'field_year_era' => ['type' => 'string_textfield', 'weight' => $weight],
    'status' => ['type' => 'boolean_checkbox', 'weight' => $weight, 'settings' => ['display_label' => TRUE]],
    'path' => ['type' => 'path', 'weight' => $weight],
    default => ['type' => 'string_textfield', 'weight' => $weight],
  };
  if (in_array($component, ['status', 'uid', 'created', 'path'], TRUE) || FieldConfig::loadByName('node', 'case_study', $component) || $component === 'body') {
    try {
      $form->setComponent($component, $widget);
    }
    catch (\Throwable $e) {
      // Skip unavailable.
    }
  }
}
$form->removeComponent('uid');
$form->removeComponent('created');
$form->save();

foreach ([
  'field_cover_image' => ['type' => 'entity_reference_entity_view', 'label' => 'hidden', 'weight' => 0, 'settings' => ['view_mode' => 'default', 'link' => FALSE]],
  'field_role' => ['type' => 'string', 'label' => 'inline', 'weight' => 1],
  'field_year_era' => ['type' => 'string', 'label' => 'inline', 'weight' => 2],
  'field_provenance' => ['type' => 'list_default', 'label' => 'inline', 'weight' => 3],
  'field_medium' => ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 4, 'settings' => ['link' => FALSE]],
  'field_constraints' => ['type' => 'basic_string', 'label' => 'above', 'weight' => 5],
  'body' => ['type' => 'text_default', 'label' => 'above', 'weight' => 6],
  'field_outcome' => ['type' => 'basic_string', 'label' => 'above', 'weight' => 7],
  'field_gallery' => ['type' => 'entity_reference_entity_view', 'label' => 'above', 'weight' => 8, 'settings' => ['view_mode' => 'default', 'link' => FALSE]],
] as $name => $opts) {
  $view->setComponent($name, $opts);
}
$view->removeComponent('links');
$view->save();

$teaser->setComponent('field_cover_image', [
  'type' => 'entity_reference_entity_view',
  'label' => 'hidden',
  'weight' => 0,
  'settings' => ['view_mode' => 'default', 'link' => FALSE],
]);
$teaser->setComponent('field_role', ['type' => 'string', 'label' => 'inline', 'weight' => 1]);
$teaser->setComponent('field_year_era', ['type' => 'string', 'label' => 'inline', 'weight' => 2]);
$teaser->setComponent('body', [
  'type' => 'text_summary_or_trimmed',
  'label' => 'hidden',
  'weight' => 3,
  'settings' => ['trim_length' => 200],
]);
$teaser->removeComponent('links');
$teaser->save();

$messenger('Form and view displays configured');

// --- Views: /work ---
if (!View::load('work')) {
  $view_config = [
    'id' => 'work',
    'label' => 'Work',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default',
        'display_title' => 'Default',
        'display_plugin' => 'default',
        'position' => 0,
        'display_options' => [
          'title' => 'Work',
          'fields' => [
            'title' => [
              'id' => 'title',
              'table' => 'node_field_data',
              'field' => 'title',
              'plugin_id' => 'field',
              'label' => '',
              'alter' => [
                'alter_text' => FALSE,
              ],
              'entity_type' => 'node',
              'entity_field' => 'title',
              'type' => 'string',
              'settings' => [
                'link_to_entity' => TRUE,
              ],
            ],
          ],
          'filters' => [
            'status' => [
              'id' => 'status',
              'table' => 'node_field_data',
              'field' => 'status',
              'plugin_id' => 'boolean',
              'entity_type' => 'node',
              'entity_field' => 'status',
              'value' => '1',
            ],
            'type' => [
              'id' => 'type',
              'table' => 'node_field_data',
              'field' => 'type',
              'plugin_id' => 'bundle',
              'entity_type' => 'node',
              'entity_field' => 'type',
              'value' => ['case_study' => 'case_study'],
            ],
          ],
          'access' => [
            'type' => 'perm',
            'options' => ['perm' => 'access content'],
          ],
          'style' => [
            'type' => 'default',
            'options' => ['row_class' => 'case-study-card', 'default_row_class' => TRUE],
          ],
          'row' => [
            'type' => 'entity:node',
            'options' => ['view_mode' => 'teaser'],
          ],
          'pager' => [
            'type' => 'none',
            'options' => ['offset' => 0],
          ],
          'sorts' => [
            'created' => [
              'id' => 'created',
              'table' => 'node_field_data',
              'field' => 'created',
              'plugin_id' => 'date',
              'entity_type' => 'node',
              'entity_field' => 'created',
              'order' => 'DESC',
            ],
          ],
        ],
      ],
      'page_work' => [
        'id' => 'page_work',
        'display_title' => 'Page',
        'display_plugin' => 'page',
        'position' => 1,
        'display_options' => [
          'path' => 'work',
          'menu' => [
            'type' => 'normal',
            'title' => 'Work',
            'weight' => 0,
            'menu_name' => 'main',
          ],
          'display_extenders' => [],
        ],
      ],
    ],
  ];
  View::create($view_config)->save();
  $messenger('Created View work at /work');
}

// --- Basic pages: Home, About ---
$page_storage = \Drupal::entityTypeManager()->getStorage('node');
$ensure_page = static function (string $title, string $body, bool $front = FALSE) use ($page_storage, $messenger): Node {
  $found = $page_storage->loadByProperties(['type' => 'page', 'title' => $title]);
  if ($found) {
    return reset($found);
  }
  $node = Node::create([
    'type' => 'page',
    'title' => $title,
    'uid' => 1,
    'status' => 1,
    'body' => [
      'value' => $body,
      'format' => 'basic_html',
    ],
  ]);
  $node->save();
  $messenger("Created page: $title");
  return $node;
};

$home = $ensure_page(
  'Home',
  '<p>Design portfolio — production design, print, and digital craft.</p><p><a href="/work">View work</a></p>'
);
$about = $ensure_page(
  'About',
  '<p>Production designer and engineer. Case studies reconstructed from surviving proofs and process notes. Historical work is labeled as such; speculative pieces are marked speculative.</p>'
);

// Front page.
\Drupal::configFactory()->getEditable('system.site')
  ->set('page.front', '/node/' . $home->id())
  ->set('name', 'Portfolio')
  ->set('slogan', 'Design case studies')
  ->save();
$messenger('Front page set to Home');

// Main menu links for Home and About if missing.
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach (
  [
    ['title' => 'Home', 'uri' => 'internal:/node/' . $home->id(), 'weight' => -10],
    ['title' => 'About', 'uri' => 'internal:/node/' . $about->id(), 'weight' => 10],
  ] as $link
) {
  $existing = $menu_link_storage->loadByProperties([
    'menu_name' => 'main',
    'title' => $link['title'],
  ]);
  if (!$existing) {
    $menu_link_storage->create([
      'title' => $link['title'],
      'link' => ['uri' => $link['uri']],
      'menu_name' => 'main',
      'weight' => $link['weight'],
    ])->save();
    $messenger('Menu link: ' . $link['title']);
  }
}

// Placeholder case studies (no invented client work).
$placeholders = [
  [
    'title' => 'Placeholder: Licensed print system',
    'role' => 'Production designer',
    'year' => '1990s–2000s',
    'constraints' => 'Licensed property rules, prepress specs, multi-SKU consistency.',
    'body' => '<p>Placeholder node. Replace with a historical case when scans and narrative are ready. Provenance: historical.</p>',
    'outcome' => 'Ship-ready mechanicals under brand and license constraints.',
    'provenance' => 'historical',
  ],
  [
    'title' => 'Placeholder: Speculative brand exercise',
    'role' => 'Designer (exercise)',
    'year' => '2026',
    'constraints' => 'Self-directed; not a client commission.',
    'body' => '<p>Placeholder speculative exercise. Marked speculative so it is never read as client work.</p>',
    'outcome' => 'Process sample for portfolio structure only.',
    'provenance' => 'speculative',
  ],
];

foreach ($placeholders as $p) {
  $found = $page_storage->loadByProperties(['type' => 'case_study', 'title' => $p['title']]);
  if ($found) {
    continue;
  }
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'medium', 'name' => $p['provenance'] === 'speculative' ? 'digital' : 'print']);
  $term = $terms ? reset($terms) : NULL;
  $node = Node::create([
    'type' => 'case_study',
    'title' => $p['title'],
    'uid' => 1,
    'status' => 1,
    'body' => ['value' => $p['body'], 'format' => 'basic_html'],
    'field_role' => $p['role'],
    'field_year_era' => $p['year'],
    'field_constraints' => $p['constraints'],
    'field_outcome' => $p['outcome'],
    'field_provenance' => $p['provenance'],
  ]);
  if ($term) {
    $node->set('field_medium', [$term->id()]);
  }
  $node->save();
  $messenger('Created case study: ' . $p['title']);
}

// Pathauto pattern for case studies.
$pathauto = \Drupal::configFactory()->getEditable('pathauto.pattern.case_study');
if ($pathauto->isNew()) {
  // Create via entity if module provides it.
}
try {
  $patterns = \Drupal::entityTypeManager()->getStorage('pathauto_pattern')->loadByProperties(['id' => 'case_study']);
  if (!$patterns) {
    $pattern = \Drupal\pathauto\Entity\PathautoPattern::create([
      'id' => 'case_study',
      'label' => 'Case study',
      'type' => 'canonical_entities:node',
      'pattern' => '/work/[node:title]',
      'weight' => -5,
    ]);
    $pattern->addSelectionCondition([
      'id' => 'entity_bundle:node',
      'bundles' => ['case_study' => 'case_study'],
      'negate' => FALSE,
      'context_mapping' => ['node' => 'node'],
    ]);
    $pattern->save();
    $messenger('Pathauto pattern case_study');
  }
}
catch (\Throwable $e) {
  $messenger('Pathauto pattern skipped: ' . $e->getMessage());
}

$messenger('DONE');
