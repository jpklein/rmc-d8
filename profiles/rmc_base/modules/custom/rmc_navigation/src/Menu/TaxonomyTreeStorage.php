<?php

namespace Drupal\rmc_navigation\Menu;

// use Drupal\Core\Config\Entity\ConfigEntityBase;
// use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
// use Drupal\taxonomy_menu\TaxonomyMenuInterface;

/**
 * Provides a vocabulary menu-tree storage using the database.
 */
class TaxonomyTreeStorage extends MenuTreeStorage implements \Drupal\Core\Menu\MenuTreeStorageInterface {

  /**
   * Loads links in the given menu, according to the given tree parameters.
   *
   * @param string $menu_name
   *   A menu name.
   * @param \Drupal\Core\Menu\MenuTreeParameters $parameters
   *   The parameters to determine which menu links to be loaded into a tree.
   *   This method will set the absolute minimum depth, which is used in
   *   MenuTreeStorage::doBuildTreeData().
   *
   * @return array
   *   A flat array of menu links that are part of the menu. Each array element
   *   is an associative array of information about the menu link, containing
   *   the fields from the {menu_tree} table. This array must be ordered
   *   depth-first.
   */
  protected function loadLinks($menu_name, MenuTreeParameters $parameters) {
// $menu_name = $menu_name === 'rmc_groups' ? 'admin' : $menu_name;

    $query = $this->connection->select($this->table, $this->options);
    $query->fields($this->table);

    // Allow a custom root to be specified for loading a menu link tree. If
    // omitted, the default root (i.e. the actual root, '') is used.
    if ($parameters->root !== '') {
      $root = $this->loadFull($parameters->root);

      // If the custom root does not exist, we cannot load the links below it.
      if (!$root) {
        return [];
      }

      // When specifying a custom root, we only want to find links whose
      // parent IDs match that of the root; that's how we ignore the rest of the
      // tree. In other words: we exclude everything unreachable from the
      // custom root.
      for ($i = 1; $i <= $root['depth']; $i++) {
        $query->condition("p$i", $root["p$i"]);
      }

      // When specifying a custom root, the menu is determined by that root.
      $menu_name = $root['menu_name'];

      // If the custom root exists, then we must rewrite some of our
      // parameters; parameters are relative to the root (default or custom),
      // but the queries require absolute numbers, so adjust correspondingly.
      if (isset($parameters->minDepth)) {
        $parameters->minDepth += $root['depth'];
      }
      else {
        $parameters->minDepth = $root['depth'];
      }
      if (isset($parameters->maxDepth)) {
        $parameters->maxDepth += $root['depth'];
      }
    }

    // If no minimum depth is specified, then set the actual minimum depth,
    // depending on the root.
    if (!isset($parameters->minDepth)) {
      if ($parameters->root !== '' && $root) {
        $parameters->minDepth = $root['depth'];
      }
      else {
        $parameters->minDepth = 1;
      }
    }

    for ($i = 1; $i <= $this->maxDepth(); $i++) {
      $query->orderBy('p' . $i, 'ASC');
    }

    $query->condition('menu_name', $menu_name);

    if (!empty($parameters->expandedParents)) {
      $query->condition('parent', $parameters->expandedParents, 'IN');
    }
    if (isset($parameters->minDepth) && $parameters->minDepth > 1) {
      $query->condition('depth', $parameters->minDepth, '>=');
    }
    if (isset($parameters->maxDepth)) {
      $query->condition('depth', $parameters->maxDepth, '<=');
    }
    // Add custom query conditions, if any were passed.
    if (!empty($parameters->conditions)) {
      // Only allow conditions that are testing definition fields.
      $parameters->conditions = array_intersect_key($parameters->conditions, array_flip($this->definitionFields()));
      $serialized_fields = $this->serializedFields();
      foreach ($parameters->conditions as $column => $value) {
        if (is_array($value)) {
          $operator = $value[1];
          $value = $value[0];
        }
        else {
          $operator = '=';
        }
        if (in_array($column, $serialized_fields)) {
          $value = serialize($value);
        }
        $query->condition($column, $value, $operator);
      }
    }

    $links = $this->safeExecuteSelect($query)->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

// $tree = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree('rmc_groups'); //, $parent, $max_depth);
// ksm($tree);
// print_r($links);
// $links = [
//   'rmc_navigation.rmc_group' => [
//     'menu_name' => 'rmc_groups',
//     'mlid' => 2,
//     'id' => 'rmc_navigation.rmc_group',
//     'parent' => 'system.admin',
//     'route_name' => 'rmc_navigation.rmc_group',
//     'route_param_key' => '',
//     'route_parameters' => 'a:0:{}',
//     'url' => '',
//     'title' => 'O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:"*string";s:7:"Content";s:12:"*arguments";a:0:{}s:10:"*options";a:0:{}}',
//     'description' => 'O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:"*string";s:24:"Find and manage content.";s:12:"*arguments";a:0:{}s:10:"*options";a:0:{}}',
//     'class' => 'Drupal\Core\Menu\MenuLinkDefault',
//     'options' => 'a:0:{}',
//     'provider' => 'system',
//     'enabled' => 1,
//     'discovered' => 1,
//     'expanded' => 0,
//     'weight' => -10,
//     'metadata' => 'a:0:{}',
//     'has_children' => 1,
//     'depth' => 2,
//     'p1' => 1,
//     'p2' => 2,
//     'p3' => 0,
//     'p4' => 0,
//     'p5' => 0,
//     'p6' => 0,
//     'p7' => 0,
//     'p8' => 0,
//     'p9' => 0,
//     'form_class' => 'Drupal\Core\Menu\Form\MenuLinkDefaultForm'
//   ],
// ];

    return $links;
  }






  /**
   * {@inheritdoc}
   */
  public function defineLinks($menu_name, $base_plugin_definition = []) {
    // Load terms for taxonomy menu vocab.
    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree($menu_name); //, $parent, $max_depth);

    $links = [];

    // Define menu links for each term in the vocabulary.
    foreach ($terms as $term) {
      if (!$term instanceof TermInterface) {
        $term = Term::load($term->tid);
      }

      $mlid = $this->buildMenuPluginId($menu_name, $term);

      $link = \Drupal::service('plugin.manager.menu.link')->getDefinition($mlid, false);

      if (empty($link)) {
        $link = $this->buildMenuDefinition($menu_name, $term, $base_plugin_definition);
        $links[$mlid] = \Drupal::service('plugin.manager.menu.link')->addDefinition($mlid, $link);
      }
    }

    return $links;
  }

  /**
   * Get the Menu Link Manager
   *
   * @return \Drupal\Core\Menu\MenuLinkManagerInterface
   *   The Menu Link Manager Service
   */
  protected function getMenuLinkManager() {
    return \Drupal::service('plugin.manager.menu.link');
  }

  /**
   * {@inheritdoc}
   */
  public function buildMenuPluginId($menu_name, TermInterface $term) {
    return 'rmc_navigation.' . $menu_name . '.' . $term->id();
  }

  /**
   * Generate a menu link plugin definition for a taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *  The taxonomy term for which to build a menu link render array.
   * @param array $base_plugin_definition
   *  The base plugin definition to merge the link with.
   *
   * @return array
   *  The menu link plugin definition.
   */
  protected function buildMenuDefinition($menu_name, TermInterface $term, $base_plugin_definition) {
    $term_id = $term->id();
    $term_url = $term->toUrl();

    // Determine parent link.
    $menu_parent_id = NULL;
    $parents = \Drupal::entityManager()->getStorage('taxonomy_term')->loadParents($term_id);
    $parents = array_values($parents);

    if (is_array($parents) && count($parents) && !is_null($parents[0]) && $parents[0] != '0') {
      $menu_parent_id = $this->buildMenuPluginId($menu_name, $parents[0]);
    }

    // Please note: if menu_parent_id is NULL, it will not update the hierarchy properly.
    // if (empty($menu_parent_id)) {
    //   $menu_parent_id = str_replace($menu_name . ':', '', $this->getMenuParent());
    // }

    // Generate link.
    $arguments = ['taxonomy_term' => $term_id];

    $link = $base_plugin_definition;

    $link += [
      'id' => $this->buildMenuPluginId($menu_name, $term),
      'title' => $term->label(),
      'description' => $term->getDescription(),
      'menu_name' => $menu_name,
      // 'expanded' => $this->expanded,
      'metadata' => [
        'taxonomy_term_id' => $term_id,
      ],
      'route_name' => $term_url->getRouteName(),
      'route_parameters' => $term_url->getRouteParameters(),
      'load arguments'  => $arguments,
      'parent' => $menu_parent_id,
      'provider' => 'rmc_navigation',
      // Use our custom class to model menu entries.
      'class' => 'Drupal\rmc_navigation\Plugin\Menu\RmcNavigationMenuLink',
      'weight' => $term->getWeight(),
    ];

    return $link;
  }

}
