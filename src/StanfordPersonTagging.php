<?php

namespace Drupal\stanford_person_tagging;

class StanfordPersonTagging {

  /**
   * Tag imported person content using workgroup membership.
   */

  /**
   *
   * Updates su_person_wg_tags field in Stanford Person content type
   * for nodes containing a sunetid.
   *
   */
  public function tagPersons()
  {
    /** @var \Drupal\stanford_samlauth\Service\WorkgroupApiInterface $workgroupApi */
    $workgroupApi = \Drupal::service('stanford_samlauth.workgroup_api');
    /** @var \Drupal\Core\Entity\EntityTypeManager $em */
    $em = \Drupal::service('entity_type.manager');
    /** @var \Drupal\Core\Entity\EntityFieldManager $fm */
    $fm = \Drupal::service('entity_field.manager');

    // Make sure we have the fields we need in the Stanford Person type.
    $all_bundle_fields = $fm
      ->getFieldDefinitions('node',
        'stanford_person');
    if (empty($all_bundle_fields['su_person_wg_tags']) ||
      empty($all_bundle_fields['su_person_sunetid'])) {
      \Drupal::logger('stanford_person_tagging')
        ->info('Person content type missing su_person_sunetid and/or su_person_wg_tags fields.');
      return false;
    }

    // Build an array of workgroups => taxonomy terms from config pages.
    $tagList = [];
    $wgList = [];
    $config_page = $em->getStorage('config_pages')
      ->loadByProperties(['type' => 'stanford_person_tagging']);
    if (!empty($config_page) && is_array($config_page)) {
      $config = reset($config_page);
      $config_vals = $config->get('su_person_tags')->getValue();
      foreach ($config_vals as $pid) {
        $paragraph = $em->getStorage('paragraph')->load($pid['target_id']);
        $wg = $paragraph->get('su_person_tagging_wg')->value;
        $terms = $paragraph->get('su_person_tagging_term')->getValue();
        if (!empty($terms) && is_array($terms)) {
          $termArray = [];
          foreach ($terms as $term) {
            if (!empty($term['target_id'])) {
              $termArray[] = $term['target_id'];
            }
          }
          if (!empty($termArray) && !empty($wg)) {
            $tagList[$wg] = $termArray;
            $wgList[] = $wg;
          }
        }
      }
      if (empty($tagList)) {
        \Drupal::logger('stanford_person_tagging')
          ->info('No workgroup tags available.');
        return false;
      }
    }
    else {
      \Drupal::logger('stanford_person_tagging')
        ->info('Unable to retrieve stanford_person_tagging config page.');
      return false;
    }

    // Get all the stanford_person nodes that have a SUNet ID
    $storage_handler = $em->getStorage('node');
    $entity_ids = $storage_handler->getQuery('AND')
      ->accessCheck(false)
      ->condition('type', 'stanford_person', '=')
      ->condition('su_person_sunetid', NULL, 'IS NOT NULL')
      ->execute();

    // For each person, get the workgroups that person belongs to.
    foreach ($entity_ids as $entity_id) {
      $personNode = $em->getStorage('node')->load((int) $entity_id);
      $sunetid = $personNode->get('su_person_sunetid')->value;
      if (!empty($sunetid)) {
        $termids = [];
        $wgs = $workgroupApi->getAllUserWorkgroups($sunetid);
        if (!empty($wgs)) {
          $memberWgs = array_intersect($wgs, $wgList);
          if (!empty($memberWgs)) {
            // For each workgroup we are tagging that the person belongs to...
            foreach ($memberWgs as $wg) {
              $terms = $tagList[$wg];
              // Get the tagging terms for that workgroup.
              foreach ($terms as $tid) {
                $found = false;
                foreach ($termids as $tidarray) {
                  if ($tidarray['target_id'] == $tid) {
                    $found = true;
                    break;
                  }
                }
                if (!$found) {
                  $termids[] = ['target_id' => $tid];
                }
              }
            }
            // Add the collected tags to that person node.
            $personNode->su_person_wg_tags = $termids;
            $personNode->save();
          }
        }
      }
    }
    return true;
  }

}
