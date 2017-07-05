<?php

use Hyn\Tenancy\Tenant\DatabaseConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameTenantsToCustomers3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection(DatabaseConnection::systemConnectionName());
        if ($schema->hasColumn('ssl_certificates', 'tenant_id')) {
            $schema->table('ssl_certificates', function (Blueprint $table) {
                $table->dropForeign('ssl_certificates_tenant_id_foreign');
                $table->renameColumn('tenant_id', 'customer_id');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection(DatabaseConnection::systemConnectionName());
        $schema->table('ssl_certificates', function (Blueprint $table) {
            $table->dropForeign('ssl_certificates_customer_id_foreign');
            $table->renameColumn('customer_id', 'tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }
}
