import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface QuotationItem {
    id: number;
    product_id: number | null;
    product_code: string;
    product_name: string;
    quantity: number;
    cost_net: number;
    cost_gross: number;
    line_total: number;
}

interface QuotationData {
    id: number;
    quotation_number: string;
    date: string;
    supplier_name: string;
    subtotal_net: number;
    iva_amount: number;
    total_amount: number;
    created_by_name: string | null;
}

interface Props {
    quotation: QuotationData;
    items: QuotationItem[];
}

export default function Show({ quotation, items }: Props) {
    const { tenant } = usePage().props as any;
    const IVA_RATE = 0.19;

    const formatCurrency = (amount: number) => {
        return '$' + Math.round(parseFloat(String(amount)) || 0).toLocaleString('es-CL');
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleDateString('es-CL');
    };

    // Calculate totals from items
    const subtotal = items.reduce((sum, item) => sum + (item.quantity * parseFloat(String(item.cost_net))), 0);
    const iva = Math.round(subtotal * IVA_RATE);
    const total = subtotal + iva;

    // QR URL
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(window.location.href)}`;

    const tenantName = tenant?.name || 'Mi Tienda';
    const tenantLogo = tenant?.logo || '';

    const exportPDF = () => {
        // @ts-ignore
        if (window.html2pdf) {
            const element = document.getElementById('invoice');
            const opt = {
                margin: 0.5,
                filename: `COTIZACION_${quotation.quotation_number}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            // @ts-ignore
            window.html2pdf().from(element).set(opt).save();
        } else {
            alert('Por favor carga la librería html2pdf');
        }
    };

    return (
        <>
            <Head title={`Cotización ${quotation.quotation_number}`} />
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

            <style dangerouslySetInnerHTML={{__html: `
                body { background: #f4f6f9; font-family: 'Roboto', Arial, sans-serif; }
                .invoice-container { max-width: 900px; margin: auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                .invoice-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
                .invoice-header img { height: 60px; }
                .invoice-header h1 { font-size: 24px; color: #2c3e50; margin: 0; }
                .invoice-info-section { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .invoice-info { font-size: 15px; color: #555; }
                .invoice-info p { margin: 3px 0; }
                .table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
                .table thead { background: #2c3e50; color: #fff; }
                .table th, .table td { padding: 10px; text-align: left; font-size: 14px; border: 1px solid #ddd; }
                .totals { margin-top: 30px; width: 100%; max-width: 400px; float: right; }
                .totals table { width: 100%; }
                .totals td { padding: 8px; font-size: 15px; }
                .totals .label { text-align: right; font-weight: bold; }
                .totals .amount { text-align: right; }
                .invoice-footer { margin-top: 60px; text-align: center; font-size: 13px; color: #777; border-top: 1px solid #eee; padding-top: 25px; clear: both; }
                .btns { max-width: 900px; margin: 20px auto; text-align: right; }
                .btn { padding: 10px 20px; margin-left: 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
                .btn-outline-primary { background: #fff; border: 1px solid #007bff; color: #007bff; }
                .btn-outline-primary:hover { background: #007bff; color: #fff; }
                .btn-outline-success { background: #fff; border: 1px solid #28a745; color: #28a745; }
                .btn-outline-success:hover { background: #28a745; color: #fff; }
                .badge-new { background: #6c757d; color: #fff; font-size: 0.7em; padding: 2px 6px; border-radius: 4px; margin-left: 8px; }
                @media print { .no-print { display: none !important; } body { background: #fff; } }
            `}} />

            <div className="btns no-print">
                <button className="btn btn-outline-primary" onClick={() => window.print()}>🖨️ Imprimir</button>
                <button className="btn btn-outline-success" onClick={exportPDF}>📄 Exportar PDF</button>
            </div>

            <div className="invoice-container" id="invoice">
                <div className="invoice-header">
                    {tenantLogo ? (
                        <img src={tenantLogo} alt="Logo" />
                    ) : (
                        <span style={{ fontSize: 24, fontWeight: 'bold' }}>{tenantName}</span>
                    )}
                    <h1>Solicitud de Cotización #{quotation.quotation_number}</h1>
                </div>

                <div className="invoice-info-section">
                    <div className="invoice-info">
                        <h5>{tenantName}</h5>
                    </div>
                    <div className="invoice-info" style={{ textAlign: 'right' }}>
                        <p><strong>Proveedor:</strong> {quotation.supplier_name}</p>
                        <p><strong>Fecha de Cotización:</strong> {formatDate(quotation.date)}</p>
                    </div>
                </div>

                <table className="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Valor Neto (Unitario)</th>
                            <th>Total Neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item, index) => (
                            <tr key={item.id}>
                                <td>{index + 1}</td>
                                <td>{item.product_code || '-'}</td>
                                <td>
                                    {item.product_name}
                                    {!item.product_id && <span className="badge-new">(Nuevo)</span>}
                                </td>
                                <td>{item.quantity}</td>
                                <td>{formatCurrency(item.cost_net)}</td>
                                <td>{formatCurrency(item.quantity * parseFloat(String(item.cost_net)))}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div style={{ display: 'flex', marginTop: 30, alignItems: 'flex-start' }}>
                    <div style={{ flex: 1 }}>
                        <img src={qrUrl} alt="QR Validación" style={{ width: 120, height: 120 }} />
                        <p style={{ fontSize: 12, color: '#666', marginTop: 10 }}>Escanee para verificar</p>
                    </div>
                    <div className="totals">
                        <table>
                            <tbody>
                                <tr>
                                    <td className="label">Subtotal Neto:</td>
                                    <td className="amount">{formatCurrency(subtotal)}</td>
                                </tr>
                                <tr>
                                    <td className="label">IVA (19%):</td>
                                    <td className="amount">{formatCurrency(iva)}</td>
                                </tr>
                                <tr>
                                    <td className="label">Total Cotización:</td>
                                    <td className="amount" style={{ fontWeight: 'bold', color: '#28a745' }}>{formatCurrency(total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="invoice-footer">
                    <p>Esta cotización fue generada automáticamente por <strong>OpenSys ERP</strong>.</p>
                </div>
            </div>
        </>
    );
}
