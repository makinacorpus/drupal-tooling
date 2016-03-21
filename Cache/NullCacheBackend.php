<?php

namespace MakinaCorpus\DrupalTooling\Cache;

class NullCacheBackend implements \DrupalCacheInterface
{
    public function get($cid) { return false; }

    public function getMultiple(&$cids) {}

    public function set($cid, $data, $expire = CACHE_PERMANENT) {}

    public function clear($cid = null, $wildcard = false) {}

    public function isEmpty() { return true; }
}
