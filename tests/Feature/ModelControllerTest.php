<?php

namespace Tests\Feature;

use App\Models\Inventory\Model;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\User;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Model Controller Feature Tests
 *
 * Verifies single and bulk operations on item definitions (Model), deletion constraints with LOTs, and authenticated media access.
 */
class ModelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_model_and_records_inventory_log(): void
    {
        $user = User::factory()->create();
        $type = Type::factory()->create();
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.inventory.models.store'), [
            'number' => 'MDL-STORE-1',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Spidol Papan Tulis',
            'specification' => 'Warna Hitam',
            'min_stock_threshold' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Model berhasil ditambahkan.');

        $model = Model::where('number', 'MDL-STORE-1')->first();
        $this->assertNotNull($model);

        $this->assertDatabaseHas('inventory_logs', [
            'model_id' => $model->id,
            'user_id' => $user->id,
            'action_type' => 'create',
            'quantity_change' => 0,
            'note' => 'Menambahkan model baru: Spidol Papan Tulis',
        ]);
        $log = InventoryLog::where('model_id', $model->id)->where('action_type', 'create')->first();
        $this->assertStringNotContainsString($model->number, $log->note);
    }

    public function test_can_destroy_model_without_lots(): void
    {
        $user = User::factory()->create();
        $model = Model::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.models.destroy', $model));

        $response->assertRedirect(route('smart.inventory'));
        $response->assertSessionHas('success', 'Model berhasil dihapus.');
        $this->assertDatabaseMissing('models', [
            'id' => $model->id,
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'user_id' => $user->id,
            'action_type' => 'delete',
            'quantity_change' => 0,
            'note' => 'Menghapus model: ' . $model->name,
        ]);
        $log = InventoryLog::where('user_id', $user->id)->where('action_type', 'delete')->first();
        $this->assertStringNotContainsString($model->number, $log->note);
    }

    public function test_cannot_destroy_model_with_lots(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $model = $lot->model;

        $response = $this->actingAs($user)->delete(route('smart.inventory.models.destroy', $model));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Model tidak dapat dihapus karena masih memiliki LOT terkait.');
        $this->assertDatabaseHas('models', [
            'id' => $model->id,
        ]);
    }

    public function test_can_update_model(): void
    {
        $user = User::factory()->create();
        $model = Model::factory()->create([
            'name' => 'Nama Old',
            'specification' => 'Spec Old',
        ]);

        $newBrand = Brand::factory()->create();
        $newUom = Uom::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.inventory.models.update', $model), [
            'number' => $model->number,
            'type_id' => $model->type_id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New',
            'specification' => 'Spec New',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Model berhasil diperbarui.');

        $this->assertDatabaseHas('models', [
            'id' => $model->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New',
            'specification' => 'Spec New',
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'model_id' => $model->id,
            'user_id' => $user->id,
            'action_type' => 'update',
        ]);

        $updateLogs = InventoryLog::where('model_id', $model->id)->where('action_type', 'update')->get();
        // Expect separate logs for name, brand, uom, and specification
        $this->assertCount(4, $updateLogs);

        $nameLog = $updateLogs->first(fn ($l) => str_contains($l->note, 'nama model'));
        $this->assertNotNull($nameLog);
        $this->assertSame("Mengubah nama model dari 'Nama Old' menjadi 'Nama New'", $nameLog->note);
        $this->assertStringNotContainsString($model->number, $nameLog->note);

        $specLog = $updateLogs->first(fn ($l) => str_contains($l->note, 'spesifikasi'));
        $this->assertNotNull($specLog);
        $this->assertSame("Mengubah spesifikasi dari 'Spec Old' menjadi 'Spec New'", $specLog->note);

        $brandLog = $updateLogs->first(fn ($l) => str_contains($l->note, 'merek'));
        $this->assertNotNull($brandLog);
        $this->assertStringContainsString($newBrand->name, $brandLog->note);

        $uomLog = $updateLogs->first(fn ($l) => str_contains($l->note, 'satuan (UoM)'));
        $this->assertNotNull($uomLog);
        $this->assertStringContainsString($newUom->name, $uomLog->note);
    }

    public function test_can_bulk_update_models(): void
    {
        $user = User::factory()->create();
        
        $model1 = Model::factory()->create([
            'name' => 'Nama Old 1',
            'specification' => 'Spec Old 1',
        ]);
        $model2 = Model::factory()->create([
            'name' => 'Nama Old 2',
            'specification' => 'Spec Old 2',
        ]);

        $newBrand = Brand::factory()->create();
        $newUom = Uom::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.inventory.models.bulk-update'), [
            'ids' => [$model1->id, $model2->id],
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '2 model terpilih berhasil diperbarui.');

        $this->assertDatabaseHas('models', [
            'id' => $model1->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $this->assertDatabaseHas('models', [
            'id' => $model2->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'model_id' => $model1->id,
            'user_id' => $user->id,
            'action_type' => 'update',
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'model_id' => $model2->id,
            'user_id' => $user->id,
            'action_type' => 'update',
        ]);
    }

    public function test_bulk_update_keeps_empty_inputs(): void
    {
        $user = User::factory()->create();
        
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $model1 = Model::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Nama Keep 1',
            'specification' => 'Spec Keep 1',
        ]);
        $model2 = Model::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Nama Keep 2',
            'specification' => 'Spec Keep 2',
        ]);

        // Bulk update sending only brand_id
        $newBrand = Brand::factory()->create();
        $response = $this->actingAs($user)->put(route('smart.inventory.models.bulk-update'), [
            'ids' => [$model1->id, $model2->id],
            'brand_id' => $newBrand->id,
            // uom_id, nama and specification are not sent/empty
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('models', [
            'id' => $model1->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $uom->id, // kept old uom
            'name' => 'Nama Keep 1', // kept old name
            'specification' => 'Spec Keep 1', // kept old spec
        ]);

        $this->assertDatabaseHas('models', [
            'id' => $model2->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $uom->id, // kept old uom
            'name' => 'Nama Keep 2', // kept old name
            'specification' => 'Spec Keep 2', // kept old spec
        ]);
    }

    public function test_can_bulk_destroy_models_without_lots(): void
    {
        $user = User::factory()->create();
        $models = Model::factory()->count(3)->create();
        $ids = $models->pluck('id')->toArray();

        $response = $this->actingAs($user)->delete(route('smart.inventory.models.bulk-destroy'), [
            'ids' => $ids,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '3 model terpilih berhasil dihapus.');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('models', [
                'id' => $id,
            ]);
        }

        $this->assertEquals(3, InventoryLog::where('user_id', $user->id)->where('action_type', 'delete')->count());
    }

    public function test_cannot_bulk_destroy_models_with_any_lots(): void
    {
        $user = User::factory()->create();
        $modelWithoutLots = Model::factory()->create();
        $lot = Lot::factory()->create([
            'model_id' => Model::factory()->create()->id,
        ]);
        $modelWithLots = $lot->model;

        $response = $this->actingAs($user)->delete(route('smart.inventory.models.bulk-destroy'), [
            'ids' => [$modelWithoutLots->id, $modelWithLots->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', "1 model terpilih berhasil dihapus.\n1 model tidak dapat dihapus karena memiliki LOT terkait.");

        $this->assertDatabaseMissing('models', [
            'id' => $modelWithoutLots->id,
        ]);
        $this->assertDatabaseHas('models', [
            'id' => $modelWithLots->id,
        ]);
    }

    public function test_media_route_requires_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('inventory/test.jpg', 'fake image content');

        // Unauthenticated request should be redirected to login
        $unauthResponse = $this->get('/media/inventory/test.jpg');
        $unauthResponse->assertRedirect(route('login'));

        // Authenticated request should serve the image
        $user = User::factory()->create();
        $authResponse = $this->actingAs($user)->get('/media/inventory/test.jpg');
        $authResponse->assertStatus(200);
        $authResponse->assertHeader('Cache-Control', 'max-age=86400, private');
    }
}
