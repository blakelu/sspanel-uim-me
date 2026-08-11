{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title"><span class="home-title">开收据</span></h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">订单 #{$order->id} · {$order->product_name|escape}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="alert alert-info">
                        收据由 <strong>{$seller_name|escape}</strong> 开具，提交后买方信息和订单金额将作为历史快照保存，不能自行修改。
                    </div>
                    <form class="card" hx-post="/user/order/{$order->id}/receipt/create" hx-swap="none">
                        <div class="card-header">
                            <h3 class="card-title">买方信息</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required" for="buyer_name">收据抬头</label>
                                    <input class="form-control" id="buyer_name" name="buyer_name" maxlength="255"
                                           required placeholder="个人姓名或公司名称">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="buyer_tax_id">税号 / TIN</label>
                                    <input class="form-control" id="buyer_tax_id" name="buyer_tax_id" maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="buyer_registration_no">统一社会信用代码 / 注册号</label>
                                    <input class="form-control" id="buyer_registration_no" name="buyer_registration_no"
                                           maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="buyer_sst_no">SST 编号</label>
                                    <input class="form-control" id="buyer_sst_no" name="buyer_sst_no" maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="buyer_contact_no">联系电话</label>
                                    <input class="form-control" id="buyer_contact_no" name="buyer_contact_no"
                                           maxlength="64" autocomplete="tel">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="buyer_email">电子邮箱</label>
                                    <input class="form-control" type="email" id="buyer_email" name="buyer_email"
                                           maxlength="255" value="{$user->email|escape}" autocomplete="email">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="buyer_address">地址</label>
                                    <textarea class="form-control" id="buyer_address" name="buyer_address" rows="3"
                                              maxlength="1024" autocomplete="street-address"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a class="btn" href="/user/order/{$order->id}/view">返回订单</a>
                            <button class="btn btn-success" type="submit" hx-disabled-elt="this">
                                <i class="icon ti ti-receipt"></i>
                                确认开具
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {include file='user/footer.tpl'}
