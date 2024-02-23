<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a kci_migrate_media_remote_video plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: kci_migrate_media_remote_video
 *     source: uri
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "kci_migrate_media_remote_video"
 * )
 */
class MediaRemoteVideo extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $uri = $value;
    // Check that the uri scheme matches youtube:// or vimeo://.
    // It's best to ensure that the source plugin has these set as the schemes
    // to use, but just in case it's not set, we'll check here.
    if (!preg_match('/^(youtube|vimeo):\/\//', $uri)) {
      // Skip this row if the uri is not a youtube or vimeo video.
      throw new MigrateSkipRowException('The uri is not a youtube or vimeo video.');
    }
    // Convert the uri into a proper URL for either youtube or vimeo.
    // The URI is expected to be in the format [youtube|vimeo]://v/[video_id].
    // Youtube videos should be converted to
    // https://www.youtube.com/watch?v=[video_id].
    // Vimeo videos should be converted to https://vimeo.com/[video_id].
    $uri_parts = explode('://v/', $uri);
    if (count($uri_parts) !== 2) {
      // Skip this row if the uri is not in the expected format.
      throw new MigrateSkipRowException('The uri ' . $uri . ' is not in the expected format of [scheme]://v/[video_id].');
    }
    $video_id = end($uri_parts);
    if ($uri_parts[0] === 'youtube') {
      $url = 'https://www.youtube.com/watch?v=' . $video_id;
    }
    else {
      $url = 'https://vimeo.com/' . $video_id;
    }
    return $url;
  }

}
