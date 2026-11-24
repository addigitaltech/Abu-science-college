<?php

namespace App\Support;

use Aws\CommandInterface;

/** R2 is private at the bucket level and does not implement S3 object ACL headers. */
final class R2ObjectAcl
{
    public static function remove(CommandInterface $command): void
    {
        if ($command->getName() === 'PutObject' && $command->hasParam('ACL')) {
            unset($command['ACL']);
        }
    }
}
