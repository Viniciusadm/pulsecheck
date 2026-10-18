<?php

namespace App\Security;

class DnsResolver
{
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $ipv4 = array_column(array_filter($records, fn (array $record) => $record['type'] === 'A'), 'ip');
        $ipv6 = array_column(array_filter($records, fn (array $record) => $record['type'] === 'AAAA'), 'ipv6');

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }
}
