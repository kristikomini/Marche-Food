<?php

namespace Tests\Feature;

use App\Models\Acquisto;
use App\Models\AuditLog;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gap A — append-only audit/change log. Every create/update/delete/restore of an
 * Auditable model is recorded with before→after field values and the acting user.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeAcquisto(): Acquisto
    {
        $fornitore = Fornitore::create(['ragione_sociale' => 'F', 'tipo' => 'alimentare']);

        return Acquisto::create([
            'fornitore_id' => $fornitore->id, 'numero_documento' => 'D1',
            'data_documento' => '2026-06-01', 'tipo_documento' => 'DDT',
        ]);
    }

    public function test_create_update_delete_restore_are_all_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $acquisto = $this->makeAcquisto();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Acquisto::class, 'auditable_id' => $acquisto->id,
            'event' => 'created', 'user_id' => $admin->id, 'etichetta' => 'D1',
        ]);

        $acquisto->update(['numero_documento' => 'D2']);
        $updated = AuditLog::where('auditable_id', $acquisto->id)->where('event', 'updated')->firstOrFail();
        $this->assertEquals('D1', $updated->changes['numero_documento']['da']);
        $this->assertEquals('D2', $updated->changes['numero_documento']['a']);

        $acquisto->delete(); // soft
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $acquisto->id, 'event' => 'deleted']);

        $acquisto->restore();
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $acquisto->id, 'event' => 'restored']);

        // restore() must NOT also emit a noisy deleted_at "updated" diff
        $this->assertEquals(1, AuditLog::where('auditable_id', $acquisto->id)->where('event', 'updated')->count());
    }

    public function test_no_op_update_is_not_logged(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $acquisto = $this->makeAcquisto();

        $acquisto->update(['numero_documento' => 'D1']); // same value → no change

        $this->assertEquals(0, AuditLog::where('auditable_id', $acquisto->id)->where('event', 'updated')->count());
    }

    public function test_force_delete_is_logged_and_label_snapshot_survives(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $acquisto = $this->makeAcquisto();
        $id = $acquisto->id;

        $acquisto->delete();
        $acquisto->forceDelete();

        $log = AuditLog::where('auditable_id', $id)->where('event', 'force_deleted')->firstOrFail();
        $this->assertEquals('D1', $log->etichetta); // readable even though the record is gone
    }

    public function test_audit_page_renders_change_log_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->makeAcquisto();

        $this->get('/audit')->assertOk();
    }
}
