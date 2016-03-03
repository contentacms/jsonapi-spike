<?php

namespace Drupal\jsonapi;

/*
   Represents a resource that has been sent to us by the client.
 */
class ResourceObject {
  public function __construct($json, $drupal, $storage) {
    $this->json = $json;
    $this->drupal = $drupal;
    $this->storage = $storage;
  }

  public function toEntity() {
    return $this->storage->create($this->drupal);
  }
}
