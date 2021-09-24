<form id="module_form" class="defaultForm form-horizontal ordermodule" action="index.php?controller=AdminModules&amp;configure=ordermodule&amp;token=0bfa54c62fa76f9ed9e36845c3892336" method="post" enctype="multipart/form-data" novalidate="">
    <input type="hidden" name="submitAddmodule" value="1">
    <div class="panel" id="fieldset_0">
        <div class="panel-heading">
            <i class="icon-cogs"></i>Settings
        </div>
        <div class="form-wrapper">
            <div class="form-group">
                <label class="control-label col-lg-3">Carriers </label>
                <div class="col-lg-9">
                    <select name="carrier" class="fixed-width-xl" id="carrier" onchange="this.form.submit()">
                        {foreach from=$carriers item=carrier}
                            <option value="{$carrier['id_carrier']}" 
                                {if $currentCarrier == $carrier['id_carrier']}
                                    selected
                                {/if}
                            >{$carrier['name']}</option>
                        {/foreach}
                    </select>
                    <p class="help-block"> Please select a carrier </p>
                </div>
            </div>
            <div class="form-group">
                <h3 class="card-header-title">Orders</h3>
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th scope="col">#</th>
                      <th scope="col">ID</th>
                      <th scope="col">Reference</th>
                      <th scope="col">Total</th>
                      <th scope="col">Payment</th>
                      <th scope="col">Date</th>
                      <th scope="col">Customer</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$orders item=order}
                        <tr>
                            {foreach from=$order key=orderDetailColumn item=orderDetail}
                                <td>{if $orderDetailColumn == 'total_paid'}${/if}{$orderDetail}</td>
                            {/foreach}
                        </tr>
                    {/foreach}
                  </tbody>
                </table>
            </div>
        </div><!-- /.form-wrapper -->
    </div>
</form>
