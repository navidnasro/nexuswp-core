<?php

namespace nexuswp\core\storage;

use nexuswp\core\Config;

class Storage
{
    private static array $readData = [];

    public static function getJsonContent(string $path): array
    {
        if (!self::isSafePath($path))
            return [];

        if (isset(self::$readData[$path]))
            return self::$readData[$path];

        if (!is_file($path) || !is_readable($path))
            return [];

        $content = file_get_contents($path);
        $content = json_decode($content,true);

        // cache only valid payloads, so a broken file does not poison later reads
        if (!is_array($content))
            return [];

        self::$readData[$path] = $content;

        return $content;
    }

    public static function getJsonDataWhere(string $path,string $key): mixed
    {
        $content = self::getJsonContent($path);
        return $content[$key] ?? false;
    }

    /**
     * only files inside the storage folder may be read
     */
    private static function isSafePath(string $path): bool
    {
        $realPath = realpath($path);
        $storageRoot = realpath(Config::storagePath());

        if ($realPath === false || $storageRoot === false)
            return false;

        return str_starts_with($realPath,$storageRoot.DIRECTORY_SEPARATOR);
    }

    public static function log(array $entry): void
    {
        $date = date('Y-m-d');

        if (!is_dir(Config::storagePath().'/logs'))
            mkdir(Config::storagePath().'/logs', 0755, true);

        $logPath = Config::storagePath().'/logs/log-'.$date.'.json';

        $entry['date'] = date('l, d/F/Y H:i:s');

        // lock the file while reading and writing, so parallel requests never lose entries
        $handle = fopen($logPath,'c+');

        if ($handle === false)
            return;

        flock($handle,LOCK_EX);

        $logs = json_decode(stream_get_contents($handle),true);
        $logs = is_array($logs) ? $logs : [];

        $entry['id'] = count($logs) + 1;

        $logs[] = $entry;

        ftruncate($handle,0);
        rewind($handle);
        fwrite($handle,json_encode($logs));
        fflush($handle);
        flock($handle,LOCK_UN);
        fclose($handle);

        // the file changed, so the read cache for this path is stale
        unset(self::$readData[$logPath]);
    }

    public static function deleteLogByTimeline(string $timeline): bool
    {
        $logFiles = glob(Config::storagePath().'/logs/log-*.json');
        $currentTime = strtotime('today'); // Current timestamp

        // Define the time range for each timeline
        $timeRanges = [
            'all' => 0,
            'last_day' => strtotime('-1 day', $currentTime),
            'last_month' => strtotime('-30 days', $currentTime),
            'last_3_months' => strtotime('-90 days', $currentTime),
            'last_6_months' => strtotime('-180 days', $currentTime),
            'last_year' => strtotime('-365 days', $currentTime),
        ];

        if (!isset($timeRanges[$timeline]))
            return false;

        $cutoffTime = $timeRanges[$timeline];
        $deleted = false;

        foreach ($logFiles as $logFile)
        {
            // Extract date from file name (e.g., log-2024-10-20.json)
            if (preg_match('/log-(\d{4}-\d{2}-\d{2})\.json/',basename($logFile),$matches))
            {
                $logDate = $matches[1];
                $logTimestamp = strtotime($logDate);

                // Check if log file matches the timeline
                if ($timeline === 'all' || ($logTimestamp >= $cutoffTime && $logTimestamp != $currentTime))
                {
                    $deleted = unlink($logFile) || $deleted;
                }
            }
        }

        return $deleted;
    }
}
