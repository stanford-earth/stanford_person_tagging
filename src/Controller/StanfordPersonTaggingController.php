<?php

namespace Drupal\stanford_person_tagging\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\stanford_person_tagging\StanfordPersonTagging;

/**
 * Tag imported person content using workgroup membership.
 */
class StanfordPersonTaggingController extends ControllerBase
{

  /**
   * Page cache kill switch.
   *
   * @var Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   *   The kill switch service.
   */
  protected $killSwitch;

  /**
   * StanfordEarthExportNewsController constructor.
   */
  public function __construct(KillSwitch $killSwitch)
  {
    $this->killSwitch = $killSwitch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * Returns stuff.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   */
  public function output(Request $request)
  {
    $tagger = new StanfordPersonTagging();
    $done = $tagger->tagPersons();
    if ($done) {
      $response = ['#markup' => 'Success!'];
    }
    else {
      $response = ['#markup' => 'Error!'];
    }
    $this->killSwitch->trigger();
    return $response;
  }

}
