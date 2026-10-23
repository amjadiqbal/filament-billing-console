<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Resources\Concerns;

use AmjadIqbal\FilamentBillingConsole\BillingConsole;
use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only resources: viewAny and view go through BillingConsole::can(); create, edit and delete are always denied.
 */
trait AuthorizesThroughConsole
{
    abstract protected static function consoleSubject(): string;

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        if (! in_array($ability, ['viewAny', 'view'], true)) {
            return Response::deny();
        }

        return BillingConsole::can(static::consoleSubject().'.'.$ability, $record) ? Response::allow() : Response::deny();
    }

    public static function can(string|UnitEnum $action, ?Model $record = null): bool
    {
        return static::getAuthorizationResponse($action, $record)->allowed();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
