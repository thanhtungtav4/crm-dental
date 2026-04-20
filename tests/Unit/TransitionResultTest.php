<?php

use App\Support\TransitionResult;
use Illuminate\Database\Eloquent\Model;

// Minimal stub model để test VO mà không cần DB
function makeStubModel(int $id = 1): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };

    $model->forceFill(['id' => $id]);

    return $model;
}

describe('TransitionResult', function (): void {
    it('stores record, fromStatus, toStatus, and transition immutably', function (): void {
        $model = makeStubModel(42);

        $result = new TransitionResult(
            record: $model,
            fromStatus: 'pending',
            toStatus: 'completed',
            transition: 'complete_visit',
            metadata: ['trigger' => 'complete_visit', 'actor_id' => 7],
        );

        expect($result->record)->toBe($model)
            ->and($result->fromStatus)->toBe('pending')
            ->and($result->toStatus)->toBe('completed')
            ->and($result->transition)->toBe('complete_visit')
            ->and($result->metadata)->toMatchArray(['trigger' => 'complete_visit', 'actor_id' => 7]);
    });

    it('reports statusChanged true when from and to differ', function (): void {
        $result = new TransitionResult(
            record: makeStubModel(),
            fromStatus: 'pending',
            toStatus: 'completed',
            transition: 'complete',
        );

        expect($result->statusChanged())->toBeTrue();
    });

    it('reports statusChanged false when from and to are the same', function (): void {
        $result = new TransitionResult(
            record: makeStubModel(),
            fromStatus: 'pending',
            toStatus: 'pending',
            transition: 'noop',
        );

        expect($result->statusChanged())->toBeFalse();
    });

    it('returns metadata value via meta() helper', function (): void {
        $result = new TransitionResult(
            record: makeStubModel(),
            fromStatus: 'a',
            toStatus: 'b',
            transition: 't',
            metadata: ['branch_id' => 5],
        );

        expect($result->meta('branch_id'))->toBe(5);
    });

    it('returns default when meta key is missing', function (): void {
        $result = new TransitionResult(
            record: makeStubModel(),
            fromStatus: 'a',
            toStatus: 'b',
            transition: 't',
        );

        expect($result->meta('missing_key', 'default'))->toBe('default');
    });

    it('serializes to array with all expected keys', function (): void {
        $model = makeStubModel(10);

        $result = new TransitionResult(
            record: $model,
            fromStatus: 'pending',
            toStatus: 'cancelled',
            transition: 'cancel',
            metadata: ['reason' => 'operator cancel'],
        );

        $array = $result->toArray();

        expect($array)->toHaveKey('record_id', 10)
            ->and($array)->toHaveKey('from_status', 'pending')
            ->and($array)->toHaveKey('to_status', 'cancelled')
            ->and($array)->toHaveKey('transition', 'cancel')
            ->and($array)->toHaveKey('status_changed', true)
            ->and($array)->toHaveKey('metadata');
    });

    it('defaults metadata to empty array when not provided', function (): void {
        $result = new TransitionResult(
            record: makeStubModel(),
            fromStatus: 'a',
            toStatus: 'b',
            transition: 't',
        );

        expect($result->metadata)->toBe([]);
    });
});
