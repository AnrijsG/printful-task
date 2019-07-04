<?php


class FileCache implements CacheInterface
{

    private const FOLDER = __DIR__ . "/../cache";
    private const LOCK_FILE_NAME = "lock";
    private const MAX_SIZE = 5 * 1024 * 1024;
    private $lock_file = null;

    private function size()
    {
        $size = 0;
        foreach (scandir(self::FOLDER) as $filename) {
            if ($filename == self::LOCK_FILE_NAME) {
                continue;
            }
            $filename = self::FOLDER . '/' . $filename;
            if (is_file($filename)) {
                $size += filesize($filename);
            }
        }
        return $size;
    }

    private function acquire_lock($exclusive = false)
    {
        if ($this->lock_file == null) {
            if (!file_exists(self::FOLDER)) {
                mkdir(self::FOLDER);
            }
            $this->lock_file = fopen(self::FOLDER . '/' . self::LOCK_FILE_NAME, 'a');
        }
        flock($this->lock_file, $exclusive ? LOCK_EX : LOCK_SH);
    }

    private function release_lock()
    {
        if ($this->lock_file != null) {
            fclose($this->lock_file);
            $this->lock_file = false;
        }
    }

    private function cleanup()
    {
        if ($this->size() > self::MAX_SIZE) {
            $this->acquire_lock(true);
            foreach (scandir(self::FOLDER) as $filename) {
                if ($filename == self::LOCK_FILE_NAME) {
                    continue;
                }
                $filename = self::FOLDER . '/' . $filename;
                if (is_file($filename)) {
                    $entries = unserialize(file_get_contents($filename));
                    for ($i = count($entries) - 1; $i >= 0; --$i) {
                        if ($entries[$i]->expires_at <= time()) {
                            array_splice($entries, $i, 1);
                        }
                    }
                    $file = fopen($filename, 'wb');
                    fwrite($file, serialize($entries));
                    fclose($file);
                }
            }
            $this->release_lock();
        }
    }

    public function set(string $key, $value, int $duration)
    {
        $this->cleanup();
        $this->acquire_lock();
        $filename = self::FOLDER . '/' . md5($key);
        if (file_exists($filename)) {
            $file = fopen($filename, 'rb+');
            flock($file, LOCK_EX);
            $entries = unserialize(stream_get_contents($file));
        } else {
            $file = fopen($filename, 'wb');
            flock($file, LOCK_EX);
            $entries = array();
        }

        $found = false;
        foreach ($entries as $i) {
            if ($i->key == $key) {
                $entry = $i;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $entry = new CacheEntry();
            array_push($entries, $entry);
            $entry->key = $key;
        }
        $entry->value = $value;
        $entry->expires_at = time() + $duration;

        $contents = serialize($entries);
        fseek($file, 0);
        fwrite($file, $contents);
        ftruncate($file, strlen($contents));

        fclose($file);
        $this->release_lock();
    }

    public function get(string $key)
    {
        $this->acquire_lock();
        $filename = self::FOLDER . '/' . md5($key);
        if (!file_exists($filename)) {
            return null;
        }
        $file = fopen($filename, 'rb');

        flock($file, LOCK_SH);

        $entries = unserialize(stream_get_contents($file));
        fclose($file);
        $this->release_lock();

        foreach ($entries as $entry) {
            if ($entry->key == $key && $entry->expires_at > time()) {
                return $entry->value;
            }
        }
        return null;
    }
}