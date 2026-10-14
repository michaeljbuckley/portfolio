<?php

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($storage->loadByProperties(['menu_name' => 'main', 'title' => 'Home']) as $link) {
  $link->delete();
  echo "Deleted menu_link_content Home\n";
}

$bs = \Drupal::entityTypeManager()->getStorage('block');
foreach ($bs->loadByProperties(['theme' => 'portfolio']) as $block) {
  $plugin = $block->getPluginId();
  if (str_contains($plugin, 'search')) {
    $block->setStatus(FALSE)->save();
    echo 'Disabled block ' . $block->id() . PHP_EOL;
  }
}

echo "OK\n";
