<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChargePaymentRequest;
use App\Models\Representative;
use App\Support\DocumentNumber;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Une fichas duplicadas de un mismo representante. #472
 * Solo une lo que es claramente la misma persona: misma cédula o mismo correo, y nombre compatible.
 * Lo dudoso (misma cédula con otro nombre, o dos cuentas de usuario) se reporta para revisar a mano.
 */
class RepresentativeMerger
{
    /**
     * @return array{merge: list<Collection<int, Representative>>, review: list<array{ids: list<int>, reason: string}>}
     */
    public function plan(): array
    {
        $representatives = Representative::query()->withCount('students')->orderBy('id')->get();
        $parent = $representatives->mapWithKeys(fn (Representative $rep) => [$rep->id => $rep->id])->all();
        $find = function (int $id) use (&$parent, &$find): int {
            return $parent[$id] === $id ? $id : ($parent[$id] = $find($parent[$id]));
        };
        $review = [];

        $byDocument = $representatives->groupBy(fn (Representative $rep) => DocumentNumber::normalize($rep->document_id) ?? '')->forget('');
        foreach ($byDocument as $group) {
            if ($group->count() < 2) {
                continue;
            }
            foreach ($group as $rep) {
                $head = $group->first(fn (Representative $other) => $other->id < $rep->id && PersonName::compatible($other->full_name, $rep->full_name));
                if ($head) {
                    $parent[$find($rep->id)] = $find($head->id);
                }
            }
            if ($group->map(fn (Representative $rep) => $find($rep->id))->unique()->count() > 1) {
                $review[] = ['ids' => $group->pluck('id')->all(), 'reason' => 'Misma cédula con nombres distintos'];
            }
        }

        $byEmail = $representatives->groupBy(fn (Representative $rep) => mb_strtolower(trim((string) $rep->email)))->forget('');
        foreach ($byEmail as $group) {
            foreach ($group as $rep) {
                $head = $group->first(fn (Representative $other) => $other->id < $rep->id
                    && PersonName::compatible($other->full_name, $rep->full_name)
                    && $this->documentsAgree($other, $rep));
                if ($head) {
                    $parent[$find($rep->id)] = $find($head->id);
                }
            }
        }

        $merge = [];
        foreach ($representatives->groupBy(fn (Representative $rep) => $find($rep->id)) as $cluster) {
            if ($cluster->count() < 2) {
                continue;
            }
            if ($cluster->pluck('user_id')->filter()->unique()->count() > 1) {
                $review[] = ['ids' => $cluster->pluck('id')->all(), 'reason' => 'Tienen cuentas de usuario distintas'];

                continue;
            }
            $merge[] = $cluster;
        }

        return ['merge' => $merge, 'review' => $review];
    }

    /** @param  Collection<int, Representative>  $cluster */
    public function merge(Collection $cluster): Representative
    {
        return DB::transaction(function () use ($cluster) {
            $survivor = $this->survivorOf($cluster);
            $others = $cluster->reject(fn (Representative $rep) => $rep->is($survivor));
            $studentIds = DB::table('student_representative')->whereIn('representative_id', $cluster->pluck('id'))->pluck('student_id')->unique()->values();

            foreach ($others as $other) {
                foreach ($survivor->getFillable() as $field) {
                    if (blank($survivor->{$field}) && filled($other->{$field})) {
                        $survivor->{$field} = $other->{$field};
                    }
                }
            }
            $survivor->save();

            $survivor->students()->syncWithoutDetaching($studentIds->all());
            $otherIds = $others->pluck('id');
            ChargePaymentRequest::query()->whereIn('representative_id', $otherIds)->update(['representative_id' => $survivor->id]);

            // Queda todo en la auditoría para poder rehacer una unión equivocada a mano.
            AuditLog::create([
                'action' => 'representatives.merged',
                'auditable_type' => Representative::class,
                'auditable_id' => $survivor->id,
                'payload' => [
                    'merged' => $others->map(fn (Representative $rep) => $rep->only(array_merge(['id'], $rep->getFillable())))->values()->all(),
                    'pivot' => DB::table('student_representative')->whereIn('representative_id', $otherIds)->get(['student_id', 'representative_id'])->all(),
                    'via' => 'representatives:merge-duplicates',
                ],
            ]);

            Representative::query()->whereIn('id', $otherIds)->delete();

            return $survivor->refresh();
        });
    }

    /** @param  Collection<int, Representative>  $cluster */
    private function survivorOf(Collection $cluster): Representative
    {
        return $cluster->sortBy([
            fn (Representative $a, Representative $b) => (int) (bool) $b->user_id <=> (int) (bool) $a->user_id,
            fn (Representative $a, Representative $b) => (int) $b->students_count <=> (int) $a->students_count,
            fn (Representative $a, Representative $b) => $a->id <=> $b->id,
        ])->first();
    }

    private function documentsAgree(Representative $a, Representative $b): bool
    {
        $first = DocumentNumber::normalize($a->document_id);
        $second = DocumentNumber::normalize($b->document_id);

        return ! $first || ! $second || $first === $second;
    }
}
