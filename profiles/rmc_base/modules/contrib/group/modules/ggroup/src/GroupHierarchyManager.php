<?php

namespace Drupal\ggroup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ggroup\Graph\GroupGraphStorageInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoader;

/**
 * Manages the relationship between groups (as subgroups).
 */
class GroupHierarchyManager implements GroupHierarchyManagerInterface {

  /**
   * The group graph storage.
   *
   * @var \Drupal\ggroup\Graph\GroupGraphStorageInterface
   */
  protected $groupGraphStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $membershipLoader;

  /**
   * Static cache for all group memberships per user.
   *
   * A nested array with all group memberships keyed by user ID.
   *
   * @var \Drupal\group\GroupMembership[]
   */
  protected $userMemberships = [];

  /**
   * Static cache for config of all installed subgroups.
   *
   * @var array[]
   */
  protected $subgroupConfig = [];

  /**
   * Static cache of all group content types for subgroup group content.
   *
   * This nested array is keyed by subgroup ID and group ID.
   *
   * @var string[][]
   */
  protected $subgroupRelations = [];

  /**
   * Static cache for all inherited subgroup role IDs per user.
   *
   * A nested array with all inherited subgroup roles keyed by user ID,
   * group ID and subgroup ID.
   *
   * @var int[][][][]
   */
  protected $inheritedSubgroupRoleIds = [];

  /**
   * Static cache for all inherited supergroup role IDs per user.
   *
   * A nested array with all inherited supergroup roles keyed by user ID,
   * group ID and supergroup ID.
   *
   * @var int[][][][]
   */
  protected $inheritedSupergroupRoleIds = [];

  /**
   * Constructs a new GroupHierarchyManager.
   *
   * @param \Drupal\ggroup\Graph\GroupGraphStorageInterface $group_graph_storage
   *   The group graph storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   */
  public function __construct(GroupGraphStorageInterface $group_graph_storage, EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoader $membership_loader) {
    $this->groupGraphStorage = $group_graph_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function addSubgroup(GroupContentInterface $group_content) {
    $plugin = $group_content->getContentPlugin();

    if ($plugin->getEntityTypeId() !== 'group') {
      throw new \InvalidArgumentException('Given group content entity does not represent a subgroup relationship.');
    }

    $parent_group = $group_content->getGroup();
    /** @var \Drupal\group\Entity\GroupInterface $child_group */
    $child_group = $group_content->getEntity();

    if ($parent_group->id() === NULL) {
      throw new \InvalidArgumentException('Parent group must be saved before it can be related to another group.');
    }

    if ($child_group->id() === NULL) {
      throw new \InvalidArgumentException('Child group must be saved before it can be related to another group.');
    }

    $new_edge_id = $this->groupGraphStorage->addEdge($parent_group->id(), $child_group->id());

    // @todo Invalidate some kind of cache?
  }

  /**
   * {@inheritdoc}
   */
  public function removeSubgroup(GroupContentInterface $group_content) {
    $plugin = $group_content->getContentPlugin();

    if ($plugin->getEntityTypeId() !== 'group') {
      throw new \InvalidArgumentException('Given group content entity does not represent a subgroup relationship.');
    }

    $parent_group = $group_content->getGroup();

    $child_group_id = $group_content->get('entity_id')->getValue();

    if (!empty($child_group_id)) {
      $child_group_id = reset($child_group_id)['target_id'];
      $this->groupGraphStorage->removeEdge($parent_group->id(), $child_group_id);
    }

    // @todo Invalidate some kind of cache?
  }

  /**
   * {@inheritdoc}
   */
  public function groupHasSubgroup(GroupInterface $group, GroupInterface $subgroup) {
    return $this->groupGraphStorage->isDescendant($subgroup->id(), $group->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSubgroups($group_id) {
    $subgroup_ids = $this->getGroupSubgroupIds($group_id);
    return $this->entityTypeManager->getStorage('group')->loadMultiple($subgroup_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSubgroupIds($group_id) {
    return $this->groupGraphStorage->getDescendants($group_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSupergroups($group_id) {
    $subgroup_ids = $this->getGroupSupergroupIds($group_id);
    return $this->entityTypeManager->getStorage('group')->loadMultiple($subgroup_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSupergroupIds($group_id) {
    return $this->groupGraphStorage->getAncestors($group_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedGroupRoles(GroupInterface $group, AccountInterface $account) {
    $role_ids = [];

    // Statically cache the memberships of a user since this method could get
    // called a lot.
    if (empty($this->userMemberships[$account->id()])) {
      $this->userMemberships[$account->id()] = $this->membershipLoader->loadByUser($account);
    }

    foreach ($this->userMemberships[$account->id()] as $membership) {
      $role_ids = array_merge($this->getInheritedSupergroupRoleIds($membership, $group), $role_ids);
      $role_ids = array_merge($this->getInheritedSubgroupRoleIds($membership, $group), $role_ids);
    }

    return $this->entityTypeManager->getStorage('group_role')->loadMultiple($role_ids);
  }

  /**
   * Get all inherited group roles for a group and a group membership.
   *
   * We map the roles down for each relation in the full path between the
   * original group and the supergroup where the user has a membership. The
   * result contains a list of all roles we have inherited from 1 or more
   * supergroups.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   The group membership for which inherited roles will be loaded.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group for which inherited roles will be loaded.
   *
   * @return string[]
   *   An array of group roles IDs for the group.
   */
  protected function getInheritedSupergroupRoleIds(GroupMembership $membership, GroupInterface $group) {
    $account_id = $membership->getGroupContent()->entity_id->target_id;
    $supergroup_id = $membership->getGroupContent()->gid->target_id;

    if (isset($this->inheritedSupergroupRoleIds[$account_id][$group->id()][$supergroup_id])) {
      return $this->inheritedSupergroupRoleIds[$account_id][$group->id()][$supergroup_id];
    }

    $role_ids = [];
    $group_roles_to_map = $this->getMembershipRoles($membership);

    // Return loaded roles if user is a direct member of the group.
    if ($supergroup_id === $group->id()) {
      return $group_roles_to_map;
    }

    $paths = $this->groupGraphStorage->getPath($supergroup_id, $group->id());
    foreach ($paths as $path) {
      $inherited_role_ids = $group_roles_to_map;
      $path_supergroup_ids_cache = [];
      // Reverse the path since the list we want to start from the
      // supergroups.
      $reversed_path = array_reverse($path);
      foreach ($reversed_path as $key => $path_supergroup_id) {
        // We reached the end of the path, return mapped role IDs for group.
        if ($path_supergroup_id === $group->id()) {
          $role_ids = array_merge($role_ids, $inherited_role_ids);
          break;
        }

        // Get the subgroup ID from the next element.
        $path_subgroup_id = isset($reversed_path[$key + 1]) ? $reversed_path[$key + 1] : NULL;

        // Get mapped roles for relation type. Filter array to remove
        // unmapped roles.
        $relation_config = $this->getSubgroupRelationConfig($path_supergroup_id, $path_subgroup_id);
        $mapped_parent_roles = array_filter($relation_config['parent_role_mapping']);

        // Check if we have a role to inherit and save a list of inherited
        // subgroup roles for the next iteration.
        $subgroup_role_ids = [];
        foreach ($inherited_role_ids as $group_role_id) {
          if (isset($mapped_parent_roles[$group_role_id])) {
            $subgroup_role_ids[] = $mapped_parent_roles[$group_role_id];
          }
        }
        $inherited_role_ids = $subgroup_role_ids;

        // Store results in static cache.
        $path_supergroup_ids_cache[] = $path_supergroup_id;
        foreach ($path_supergroup_ids_cache as $path_supergroup_id) {
          $this->inheritedSupergroupRoleIds[$account_id][$path_subgroup_id][$path_supergroup_id] = $inherited_role_ids;
        }
      }
    }

    return $this->inheritedSupergroupRoleIds[$account_id][$group->id()][$supergroup_id] = $role_ids;
  }

  /**
   * Get all inherited group roles for a group and a group membership.
   *
   * We map the roles up for each relation in the full path between the
   * original group and the subgroup where the user has a membership. The
   * result contains a list of all roles we have inherited from 1 or more
   * subgroups.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   The subgroup membership from which inherited roles will be loaded.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group for which inherited roles will be loaded.
   *
   * @return string[]
   *   An array of group roles IDs for the group.
   */
  protected function getInheritedSubgroupRoleIds(GroupMembership $membership, GroupInterface $group) {
    $account_id = $membership->getGroupContent()->entity_id->target_id;
    $subgroup_id = $membership->getGroupContent()->gid->target_id;

    if (isset($this->inheritedSubgroupRoleIds[$account_id][$group->id()][$subgroup_id])) {
      return $this->inheritedSubgroupRoleIds[$account_id][$group->id()][$subgroup_id];
    }

    $role_ids = [];
    $group_roles_to_map = $this->getMembershipRoles($membership);

    // Return loaded roles if user is a direct member of the group.
    if ($subgroup_id === $group->id()) {
      return $group_roles_to_map;
    }

    $paths = $this->groupGraphStorage->getPath($group->id(), $subgroup_id);
    foreach ($paths as $path) {
      $inherited_role_ids = $group_roles_to_map;
      $path_subgroup_ids_cache = [];
      foreach ($path as $key => $path_subgroup_id) {
        // We reached the end of the path, store mapped role IDs.
        if ($path_subgroup_id === $group->id()) {
          $role_ids = array_merge($role_ids, $inherited_role_ids);
          break;
        }

        // Get the subgroup ID from the next element.
        $path_supergroup_id = isset($path[$key + 1]) ? $path[$key + 1] : NULL;

        // Get mapped roles for relation type. Filter array to remove
        // unmapped roles.
        $relation_config = $this->getSubgroupRelationConfig($path_supergroup_id, $path_subgroup_id);
        $mapped_child_roles = array_filter($relation_config['child_role_mapping']);

        // Check if we have a role to inherit and save a list of inherited
        // supergroup roles for the next iteration.
        $supergroup_role_ids = [];
        foreach ($inherited_role_ids as $group_role_id) {
          if (isset($mapped_child_roles[$group_role_id])) {
            $supergroup_role_ids[] = $mapped_child_roles[$group_role_id];
          }
        }
        $inherited_role_ids = $supergroup_role_ids;

        // Store results in static cache.
        $path_subgroup_ids_cache[] = $path_subgroup_id;
        foreach ($path_subgroup_ids_cache as $path_subgroup_id) {
          $this->inheritedSubgroupRoleIds[$account_id][$path_supergroup_id][$path_subgroup_id] = $inherited_role_ids;
        }
      }
    }

    return $this->inheritedSubgroupRoleIds[$account_id][$group->id()][$subgroup_id] = $role_ids;
  }

  /**
   * Get the role IDs for a group membership.
   *
   * @param \Drupal\group\GroupMembership $membership
   *   The user to load the membership for.
   *
   * @return string[]
   *   An array of role IDs.
   */
  protected function getMembershipRoles(GroupMembership $membership) {
    $ids = [];
    foreach ($membership->getGroupContent()->group_roles as $group_role_ref) {
      $ids[] = $group_role_ref->target_id;
    }

    // We add the implied member role. Usually we should get this from the
    // membership $membership->getGroup()->getGrouptype()->getMemberRoleID(),
    // but since this means the whole Group and GroupType entities need to be
    // loaded, this has a big impact on performance.
    // @todo: Fix this hacky solution!
    $ids[] = str_replace('-group_membership', '', $membership->getGroupContent()->bundle()) . '-member';

    return $ids;
  }

  /**
   * Get the config for all installed subgroup relations.
   *
   * @return array[]
   *   A nested array with configuration values keyed by subgroup relation ID.
   */
  protected function getSubgroupRelationsConfig() {
    // We create a static cache with the configuration for all subgroup
    // relations since having separate queries for every relation has a big
    // impact on performance.
    if (!$this->subgroupConfig) {
      foreach ($this->entityTypeManager->getStorage('group_type')->loadMultiple() as $group_type) {
        $plugin_id = 'subgroup:' . $group_type->id();
        /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('group_content_type');
        $subgroup_content_types = $storage->loadByContentPluginId($plugin_id);
        foreach ($subgroup_content_types as $subgroup_content_type) {
          /** @var \Drupal\group\Entity\GroupContentTypeInterface $subgroup_content_type */
          $this->subgroupConfig[$subgroup_content_type->id()] = $subgroup_content_type->getContentPlugin()->getConfiguration();
        }
      }
    }
    return $this->subgroupConfig;
  }

  /**
   * Get the config for a relation between a group and a subgroup.
   *
   * @param int $group_id
   *   The group for which to get the configuration.
   * @param int $subgroup_id
   *   The subgroup for which to get the configuration.
   *
   * @return array[]
   *   A nested array with configuration values.
   */
  protected function getSubgroupRelationConfig($group_id, $subgroup_id) {
    $subgroup_relations_config = $this->getSubgroupRelationsConfig();

    // We need the type of each relation to fetch the configuration. We create
    // a static cache for the types of all subgroup relations since fetching
    // each relation independently has a big impact on performance.
    if (!$this->subgroupRelations) {
      // Get all  type between the supergroup and subgroup.
      $group_contents = $this->entityTypeManager->getStorage('group_content')
        ->loadByProperties([
          'type' => array_keys($subgroup_relations_config),
        ]);
      foreach ($group_contents as $group_content) {
        $this->subgroupRelations[$group_content->entity_id->target_id][$group_content->gid->target_id] = $group_content->bundle();
      }
    }

    $type = $this->subgroupRelations[$subgroup_id][$group_id];
    return $subgroup_relations_config[$type];
  }

}
