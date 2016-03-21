<?php
/**
 * Null implementation for lock.inc
 */

function lock_initialize() {}

function _lock_id() {
  static $lockId;
  if (!$lockId) {
    $lockId = uniqid(mt_rand(), true);
  }
  return $lockId;
}

function lock_acquire($name, $timeout = 30.0) {
  return true;
}

function lock_may_be_available($name) {
  return true;
}

function lock_wait($name, $delay = 30) {
  return false;
}

function lock_release($name) {}

function lock_release_all($lock_id = null) {}
