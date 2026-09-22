<?php

namespace Tests\Feature;

use App\Mail\DMUnitStatusRequest;
use App\Mail\ManagerRequestApprovalMail;
use App\Mail\RequesterRequestRejectedMail;
use App\Models\HrdEmployee;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_unit_status_request_email_renders_in_english(): void
    {
        $requester = User::factory()->create(['name' => 'Alice Requester']);
        $cat = Category::factory()->create();
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create(['name' => 'Dell']);
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'name' => 'Monitor 27 inch',
        ]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $unit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'number' => 'AST-DEL-001',
            'condition' => 'Good',
        ]);

        $approval = UnitStatusApproval::create([
            'unit_id' => $unit->id,
            'requester_id' => $requester->id,
            'previous_condition' => 'Good',
            'proposed_condition' => 'Damaged',
            'note' => 'Display cracked during relocation',
            'decision' => 'pending',
            'memo_url' => 'memos/test.pdf',
            'requested_at' => now(),
        ]);

        $mail = new DMUnitStatusRequest($unit, $approval, 'Jane Manager');
        $rendered = $mail->render();

        $this->assertStringContainsString('lang="en"', $rendered);
        $this->assertStringContainsString('Asset Status Approval - SMART', $rendered);
        $this->assertStringContainsString('Asset Management & Request Tracking System', $rendered);
        $this->assertStringContainsString('Dear Jane Manager', $rendered);
        $this->assertStringContainsString('The asset deactivation request below has been approved by the', $rendered);
        $this->assertStringContainsString('Asset Number', $rendered);
        $this->assertStringContainsString('AST-DEL-001', $rendered);
        $this->assertStringContainsString('Item Name', $rendered);
        $this->assertStringContainsString('Dell Monitor 27 inch', $rendered);
        $this->assertStringContainsString('Asset Location', $rendered);
        $this->assertStringContainsString('Initial Condition', $rendered);
        $this->assertStringContainsString('Proposed Condition', $rendered);
        $this->assertStringContainsString('(Inactive)', $rendered);
        $this->assertStringContainsString('Requested By', $rendered);
        $this->assertStringContainsString('Alice Requester', $rendered);
        $this->assertStringContainsString('Notes', $rendered);
        $this->assertStringContainsString('Display cracked during relocation', $rendered);
        $this->assertStringContainsString('Review & Provide Decision', $rendered);
        $this->assertStringContainsString('If the button above does not work, open the following link in your browser:', $rendered);
        $this->assertStringContainsString('Automated email from <strong>SMART</strong> &bull; Please do not reply to this email.', $rendered);
    }

    public function test_manager_request_approval_email_renders_in_english(): void
    {
        $employee = HrdEmployee::factory()->create(['email' => 'manager@test.com']);
        $manager = User::factory()->create(['name' => 'Bob Manager', 'employee_id' => $employee->employee_id]);
        $requester = User::factory()->create(['name' => 'Charlie Requester']);

        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id, 'name' => 'Laptops']);
        $brand = Brand::factory()->create(['name' => 'Lenovo']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad T14',
            'specification' => '16GB RAM, 512GB SSD',
        ]);

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000010',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Need laptop for business trip',
            'status' => 'wait',
        ]);

        RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'quantity_requested' => 1,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(5),
        ]);

        $mail = new ManagerRequestApprovalMail($req, $manager, 'Peminjaman');
        $rendered = $mail->render();

        $this->assertStringContainsString('lang="en"', $rendered);
        $this->assertStringContainsString('Borrowing Approval - SMART', $rendered);
        $this->assertStringContainsString('Stock Management and Request Tracking', $rendered);
        $this->assertStringContainsString('Dear Bob Manager', $rendered);
        $this->assertStringContainsString('There is a new <strong>borrowing</strong> submitted by <strong>Charlie Requester</strong>', $rendered);
        $this->assertStringContainsString('Request Number', $rendered);
        $this->assertStringContainsString('REQ-0000010', $rendered);
        $this->assertStringContainsString('Type', $rendered);
        $this->assertStringContainsString('Borrowing', $rendered);
        $this->assertStringContainsString('Requester', $rendered);
        $this->assertStringContainsString('Utilization', $rendered);
        $this->assertStringContainsString('Borrowing Period', $rendered);
        $this->assertStringContainsString('Reason', $rendered);
        $this->assertStringContainsString('Need laptop for business trip', $rendered);
        $this->assertStringContainsString('Requested items:', $rendered);
        $this->assertStringContainsString('Item Name', $rendered);
        $this->assertStringContainsString('Category', $rendered);
        $this->assertStringContainsString('Quantity', $rendered);
        $this->assertStringContainsString('Lenovo ThinkPad T14', $rendered);
        $this->assertStringContainsString('16GB RAM, 512GB SSD', $rendered);
        $this->assertStringContainsString('Review & Provide Decision', $rendered);
        $this->assertStringContainsString('*This link is secure and valid for 48 hours.', $rendered);
        $this->assertStringContainsString('If the button above does not work or the link has expired', $rendered);
        $this->assertStringContainsString('Automated email from <strong>SMART</strong> &bull; Please do not reply to this email.', $rendered);
    }

    public function test_requester_request_rejected_email_renders_in_english(): void
    {
        $employee = HrdEmployee::factory()->create(['email' => 'manager@test.com']);
        $manager = User::factory()->create(['name' => 'Bob Manager', 'employee_id' => $employee->employee_id]);
        $requester = User::factory()->create(['name' => 'Charlie Requester']);

        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id, 'name' => 'Supplies']);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create(['name' => 'Pack']);
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'A4 Paper',
        ]);

        $req = SmartRequest::create([
            'request_number' => 'REQ-0000011',
            'user_id' => $requester->id,
            'approver_id' => $manager->id,
            'utilization' => 'corporate',
            'org_id' => $requester->hrdEmployee->orgchart_id,
            'reasoning' => 'Monthly printing stock',
            'status' => 'reject',
        ]);

        RequestItem::create([
            'request_id' => $req->id,
            'barang_id' => $barang->id,
            'quantity_requested' => 3,
        ]);

        $mail = new RequesterRequestRejectedMail($req, $manager, 'Budget quota exceeded');
        $rendered = $mail->render();

        $this->assertStringContainsString('lang="en"', $rendered);
        $this->assertStringContainsString('Request Rejected - SMART', $rendered);
        $this->assertStringContainsString('Stock Management and Request Tracking', $rendered);
        $this->assertStringContainsString('Dear Charlie Requester', $rendered);
        $this->assertStringContainsString('Your request with number <strong>REQ-0000011</strong> has been <strong style="color: #dc2626;">rejected</strong> by <strong>Bob Manager</strong>', $rendered);
        $this->assertStringContainsString('Budget quota exceeded', $rendered);
        $this->assertStringContainsString('Request Number', $rendered);
        $this->assertStringContainsString('Type', $rendered);
        $this->assertStringContainsString('Request', $rendered);
        $this->assertStringContainsString('Requester', $rendered);
        $this->assertStringContainsString('Utilization', $rendered);
        $this->assertStringContainsString('Reason for Request', $rendered);
        $this->assertStringContainsString('Requested items:', $rendered);
        $this->assertStringContainsString('Item Name', $rendered);
        $this->assertStringContainsString('Category', $rendered);
        $this->assertStringContainsString('Quantity', $rendered);
        $this->assertStringContainsString('View Request Details', $rendered);
        $this->assertStringContainsString('If the button above does not work, you can view the request details using the following link:', $rendered);
        $this->assertStringContainsString('Automated email from <strong>SMART</strong> &bull; Please do not reply to this email.', $rendered);
    }
}
