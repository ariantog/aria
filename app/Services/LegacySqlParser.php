<?php

namespace App\Services;

class LegacySqlParser
{
    public function parseFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("SQL file not readable: {$path}");
        }

        $sql = file_get_contents($path);

        return [
            'acl' => $this->parseAcl($sql),
            'roles' => $this->parseRoles($sql),
            'users' => $this->parseUsers($sql),
            'locations' => $this->parseLocations($sql),
            'location_customer' => $this->parseLocationCustomer($sql),
        ];
    }

    /**
     * @return list<array{role_id: int, action: string, app_id: int}>
     */
    public function parseAcl(string $sql): array
    {
        $rows = [];

        foreach ($this->extractInsertBlocks($sql, 'acl') as $block) {
            if (preg_match_all("/\((\d+),\s*'([^']+)',\s*(\d+),/", $block, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rows[] = [
                        'role_id' => (int) $match[1],
                        'action' => $match[2],
                        'app_id' => (int) $match[3],
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function parseRoles(string $sql): array
    {
        $rows = [];

        foreach ($this->extractInsertBlocks($sql, 'roles') as $block) {
            if (preg_match_all("/\((\d+),\s*'([^']+)'/", $block, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rows[] = [
                        'id' => (int) $match[1],
                        'name' => $match[2],
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, username: string, password: string, active: int, role_id: int, location_id: int}>
     */
    public function parseUsers(string $sql): array
    {
        $rows = [];

        foreach ($this->extractInsertBlocks($sql, 'users') as $block) {
            if (preg_match_all(
                "/\((\d+),\s*'([^']+)',\s*'([^']+)',\s*(\d+),\s*(\d+),\s*(\d+),/",
                $block,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $rows[] = [
                        'id' => (int) $match[1],
                        'username' => $match[2],
                        'password' => $match[3],
                        'active' => (int) $match[4],
                        'role_id' => (int) $match[5],
                        'location_id' => (int) $match[6],
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function parseLocations(string $sql): array
    {
        $rows = [];

        foreach ($this->extractInsertBlocks($sql, 'locations') as $block) {
            if (preg_match_all("/\((\d+),\s*'([^']+)'/", $block, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rows[] = [
                        'id' => (int) $match[1],
                        'name' => $match[2],
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{location_id: int, customer_id: int}>
     */
    public function parseLocationCustomer(string $sql): array
    {
        $rows = [];

        foreach ($this->extractInsertBlocks($sql, 'location_customer') as $block) {
            if (preg_match_all("/\((\d+),\s*(\d+)\)/", $block, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rows[] = [
                        'location_id' => (int) $match[1],
                        'customer_id' => (int) $match[2],
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function extractInsertBlocks(string $sql, string $table): array
    {
        $blocks = [];
        $needle = 'INSERT INTO `'.$table.'`';
        $offset = 0;

        while (($start = strpos($sql, $needle, $offset)) !== false) {
            $end = $this->findInsertStatementEnd($sql, $start);
            $blocks[] = substr($sql, $start, $end - $start);
            $offset = $end;
        }

        return $blocks;
    }

    private function findInsertStatementEnd(string $sql, int $start): int
    {
        $length = strlen($sql);
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === "'") {
                    $inString = false;
                }

                continue;
            }

            if ($char === "'") {
                $inString = true;

                continue;
            }

            if ($char === ';') {
                return $i + 1;
            }
        }

        return $length;
    }
}
