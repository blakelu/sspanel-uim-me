{include file='user/header.tpl'}

<style>
    .receipt-stage {
        overflow-x: auto;
        padding-bottom: 1rem;
    }

    .receipt-sheet {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto;
        padding: 16mm 12mm 14mm;
        box-sizing: border-box;
        background: #fff;
        color: #111;
        font-family: Arial, "Noto Sans SC", "Microsoft YaHei", sans-serif;
        font-size: 10pt;
        line-height: 1.45;
        box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.12);
    }

    .receipt-seller {
        display: grid;
        grid-template-columns: 29mm 1fr;
        gap: 8mm;
        align-items: center;
    }

    .receipt-logo {
        width: 27mm;
        height: 27mm;
        object-fit: contain;
    }

    .receipt-seller-name {
        margin: 0 0 2mm;
        font-size: 18pt;
        line-height: 1.2;
        font-weight: 700;
    }

    .receipt-seller-meta {
        margin-top: 1.2mm;
    }

    .receipt-rule {
        margin: 7mm 0 9mm;
        border-top: 0.25mm solid #222;
    }

    .receipt-title {
        margin: 0 0 8mm;
        text-align: center;
        font-size: 20pt;
        font-weight: 600;
        letter-spacing: 0.8mm;
    }

    .receipt-parties {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 0.9fr);
        gap: 12mm;
        margin: 0 1mm 7mm;
    }

    .receipt-fields {
        display: grid;
        grid-template-columns: 42mm 1fr;
        gap: 2.5mm 3mm;
    }

    .receipt-fields.compact {
        grid-template-columns: 28mm 1fr;
    }

    .receipt-label {
        color: #333;
        font-size: 8.5pt;
        font-weight: 600;
        text-transform: uppercase;
    }

    .receipt-value {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .receipt-items {
        width: 100%;
        margin-top: 4mm;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .receipt-items th,
    .receipt-items td {
        padding: 3.5mm 2mm;
        text-align: right;
        vertical-align: top;
    }

    .receipt-items th {
        border-top: 0.3mm solid #222;
        border-bottom: 0.2mm solid #888;
        font-size: 8.5pt;
        font-weight: 600;
        white-space: nowrap;
    }

    .receipt-items td {
        border-bottom: 0.2mm dashed #888;
    }

    .receipt-items th:nth-child(1),
    .receipt-items td:nth-child(1),
    .receipt-items th:nth-child(2),
    .receipt-items td:nth-child(2) {
        text-align: left;
    }

    .receipt-summary-wrap {
        display: flex;
        justify-content: flex-end;
        margin-top: 10mm;
    }

    .receipt-summary {
        width: 88mm;
    }

    .receipt-summary-title {
        padding: 0 2mm 3mm;
        border-bottom: 0.25mm solid #222;
        text-align: right;
        font-size: 12pt;
    }

    .receipt-summary-row {
        display: grid;
        grid-template-columns: 1fr 34mm;
        gap: 4mm;
        padding: 1.5mm 2mm 0;
    }

    .receipt-summary-row span:last-child {
        text-align: right;
    }

    .receipt-summary-row.total {
        margin-top: 1mm;
        padding-top: 2.5mm;
        border-top: 0.2mm solid #aaa;
        font-weight: 700;
    }

    .receipt-thanks {
        margin-top: 14mm;
        text-align: center;
        font-size: 15pt;
        font-weight: 500;
    }

    @media print {
        .receipt-stage {
            overflow: visible;
        }

        .receipt-sheet {
            margin: 0;
            box-shadow: none;
        }
    }
</style>

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title"><span class="home-title">收据 {$receipt->receipt_no|escape}</span></h2>
                    <div class="page-pretitle my-3"><span class="home-subtitle">电子收据</span></div>
                </div>
                <div class="col-auto">
                    <div class="btn-list">
                        <a class="btn" href="/user/order/{$receipt->order_id}/view">返回订单</a>
                        <button class="btn btn-primary" id="download-receipt" type="button"
                                data-filename="receipt-{$receipt->receipt_no|escape}.pdf">
                            <i class="icon ti ti-download"></i>
                            下载 PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl receipt-stage">
            <article class="receipt-sheet" id="receipt-sheet">
                <header class="receipt-seller">
                    <img class="receipt-logo" src="{$receipt->seller_logo|escape}" alt="">
                    <div>
                        <h1 class="receipt-seller-name">{$receipt->seller_name|escape}</h1>
                        {if $receipt->seller_registration_no !== ''}
                            <div class="receipt-seller-meta">Business Registration No.: {$receipt->seller_registration_no|escape}</div>
                        {/if}
                        {if $receipt->seller_address !== ''}
                            <div class="receipt-seller-meta">{$receipt->seller_address|escape}</div>
                        {/if}
                        {if $receipt->seller_tax_id !== ''}
                            <div class="receipt-seller-meta">Tax Identification Number (TIN): {$receipt->seller_tax_id|escape}</div>
                        {/if}
                        {if $receipt->seller_sst_no !== ''}
                            <div class="receipt-seller-meta">SST Registration No.: {$receipt->seller_sst_no|escape}</div>
                        {/if}
                    </div>
                </header>

                <div class="receipt-rule"></div>
                <h2 class="receipt-title">E - RECEIPT</h2>

                <section class="receipt-parties">
                    <div class="receipt-fields">
                        <span class="receipt-label">Buyer Name</span>
                        <strong class="receipt-value">{$receipt->buyer_name|escape}</strong>
                        <span class="receipt-label">TIN</span>
                        <span class="receipt-value">{$receipt->buyer_tax_id|default:'NA'|escape}</span>
                        <span class="receipt-label">Registration No.</span>
                        <span class="receipt-value">{$receipt->buyer_registration_no|default:'NA'|escape}</span>
                        <span class="receipt-label">Contact No.</span>
                        <span class="receipt-value">{$receipt->buyer_contact_no|default:'NA'|escape}</span>
                        <span class="receipt-label">Email</span>
                        <span class="receipt-value">{$receipt->buyer_email|default:'NA'|escape}</span>
                        <span class="receipt-label">SST No.</span>
                        <span class="receipt-value">{$receipt->buyer_sst_no|default:'NA'|escape}</span>
                        <span class="receipt-label">Address</span>
                        <span class="receipt-value">{$receipt->buyer_address|default:'NA'|escape|nl2br}</span>
                    </div>
                    <div class="receipt-fields compact">
                        <span class="receipt-label">Receipt No.</span>
                        <strong class="receipt-value">{$receipt->receipt_no|escape}</strong>
                        <span class="receipt-label">Date</span>
                        <span class="receipt-value">{$receipt->date|escape}</span>
                        <span class="receipt-label">Currency</span>
                        <span class="receipt-value">{$receipt->currency|escape}</span>
                        <span class="receipt-label">Order No.</span>
                        <span class="receipt-value">#{$receipt->order_id}</span>
                    </div>
                </section>

                <table class="receipt-items">
                    <colgroup>
                        <col style="width: 8%">
                        <col style="width: 34%">
                        <col style="width: 8%">
                        <col style="width: 14%">
                        <col style="width: 12%">
                        <col style="width: 12%">
                        <col style="width: 12%">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>No.</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Discount</th>
                        <th>Tax</th>
                        <th>Net Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>001</td>
                        <td>{$receipt->item_name|escape}</td>
                        <td>{$receipt->quantity}</td>
                        <td>{$receipt->unit_price_text}</td>
                        <td>{$receipt->discount_text}</td>
                        <td>{$receipt->tax_amount_text}</td>
                        <td>{$receipt->total_text}</td>
                    </tr>
                    </tbody>
                </table>

                <section class="receipt-summary-wrap">
                    <div class="receipt-summary">
                        <div class="receipt-summary-title">AMOUNT ({$receipt->currency|escape})</div>
                        <div class="receipt-summary-row">
                            <span>Total Excluding Tax</span><span>{$receipt->subtotal_text}</span>
                        </div>
                        <div class="receipt-summary-row">
                            <span>Tax Region</span><span>{$receipt->tax_region|escape}</span>
                        </div>
                        <div class="receipt-summary-row">
                            <span>Tax Type</span><span>{$receipt->tax_type|escape}</span>
                        </div>
                        <div class="receipt-summary-row">
                            <span>Tax Rate</span><span>{$receipt->tax_rate_text}%</span>
                        </div>
                        <div class="receipt-summary-row">
                            <span>Tax Amount</span><span>{$receipt->tax_amount_text}</span>
                        </div>
                        <div class="receipt-summary-row total">
                            <span>Total Including Tax</span><span>{$receipt->total_text}</span>
                        </div>
                    </div>
                </section>

                <footer class="receipt-thanks">{$receipt->footer|escape}</footer>
            </article>
        </div>
    </div>

    <script src="//{$config['jsdelivr_url']}/npm/html2pdf.js@0.10.3/dist/html2pdf.bundle.min.js"></script>
    <script>
        document.getElementById('download-receipt').addEventListener('click', async function () {
            const button = this;
            const sheet = document.getElementById('receipt-sheet');
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>正在生成';

            try {
                const images = Array.from(sheet.querySelectorAll('img'));
                await Promise.all(images.map(function (img) {
                    if (img.complete) {
                        return Promise.resolve();
                    }

                    return new Promise(function (resolve) {
                        img.addEventListener('load', resolve, { once: true });
                        img.addEventListener('error', resolve, { once: true });
                    });
                }));

                await html2pdf().set({
                    margin: 0,
                    filename: button.dataset.filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                }).from(sheet).save();
            } catch (error) {
                alert('PDF 生成失败，请刷新页面后重试。');
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        });
    </script>

    {include file='user/footer.tpl'}
