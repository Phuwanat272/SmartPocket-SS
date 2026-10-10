// assets/js/main.js - SmartPocket SS
// Chart.js Initializer + Interactive UI

document.addEventListener('DOMContentLoaded', () => {

    // ── 1. Income Doughnut Chart ───────────────────────────
    const ctxIncome = document.getElementById('incomeChart');
    if (ctxIncome && typeof incomeChartData !== 'undefined') {
        const hasData = incomeChartData.data.length > 0 && incomeChartData.data.some(v => v > 0);
        new Chart(ctxIncome, {
            type: 'doughnut',
            data: {
                labels: hasData ? incomeChartData.labels : ['ไม่มีข้อมูล'],
                datasets: [{
                    data: hasData ? incomeChartData.data : [1],
                    backgroundColor: hasData ? [
                        '#0d6efd','#3b82f6','#60a5fa','#93c5fd','#1d4ed8','#2563eb','#7dd3fc'
                    ] : ['#e2e8f0'],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: hasData,
                        callbacks: {
                            label: ctx => ' ฿' + Number(ctx.parsed).toLocaleString('th-TH', {minimumFractionDigits: 2})
                        }
                    }
                }
            }
        });
    }

    // ── 2. Expense Doughnut Chart ──────────────────────────
    const ctxExpense = document.getElementById('expenseChart');
    if (ctxExpense && typeof expenseChartData !== 'undefined') {
        const hasData = expenseChartData.data.length > 0 && expenseChartData.data.some(v => v > 0);
        new Chart(ctxExpense, {
            type: 'doughnut',
            data: {
                labels: hasData ? expenseChartData.labels : ['ไม่มีข้อมูล'],
                datasets: [{
                    data: hasData ? expenseChartData.data : [1],
                    backgroundColor: hasData ? [
                        '#ef4444','#f97316','#eab308','#ec4899','#8b5cf6','#6366f1','#f43f5e'
                    ] : ['#e2e8f0'],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: hasData,
                        callbacks: {
                            label: ctx => ' ฿' + Number(ctx.parsed).toLocaleString('th-TH', {minimumFractionDigits: 2})
                        }
                    }
                }
            }
        });
    }

    // ── 3. Auto-dismiss alerts after 5 seconds ─────────────
    document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // ── 4. Transaction type radio card highlight ───────────
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('input[name="type"]').forEach(r => {
                r.closest('.form-check').classList.remove('border-primary', 'border-danger', 'bg-primary-subtle', 'bg-danger-subtle');
            });
            const selectedCard = radio.closest('.form-check');
            if (radio.value === 'income') {
                selectedCard.classList.add('border-primary', 'bg-primary-subtle');
            } else {
                selectedCard.classList.add('border-danger', 'bg-danger-subtle');
            }
        });
        // Trigger on load to highlight default
        if (radio.checked) radio.dispatchEvent(new Event('change'));
    });

    // ── 5. Delete confirmation with custom modal-style confirm ──
    document.querySelectorAll('form.delete-form').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('ยืนยันการลบรายการนี้?\n(ไม่สามารถกู้คืนได้)')) {
                e.preventDefault();
            }
        });
    });

});
