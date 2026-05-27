import type { jsPDF } from 'jspdf';
import type { Row } from 'exceljs';
import { SurveyResponse, DashboardStats } from '../types';
import { createLogger } from './logger';

const logger = createLogger('exportUtils');

const escapeHtml = (str: string | null | undefined): string => {
  if (str == null) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
};

// Extend jsPDF type for lastAutoTable
declare module 'jspdf' {
  interface jsPDF {
    lastAutoTable: { finalY: number };
  }
}

// Helper function to format date
const formatDate = (date: string | Date): string => {
  return new Date(date).toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

// Helper function to format datetime
const formatDateTime = (date: string | Date): string => {
  return new Date(date).toLocaleString('ar-SA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Get satisfaction level label
const getSatisfactionLevel = (score: number): string => {
  if (score >= 85) return 'ممتاز';
  if (score >= 70) return 'جيد';
  if (score >= 50) return 'متوسط';
  return 'ضعيف';
};

/**
 * Draw a professional footer on the current page
 */
const drawFooter = (doc: jsPDF, pageWidth: number, pageHeight: number, hospitalName: string, margin: number) => {
  const pageNum = doc.internal.pages.length - 1;
  doc.setFontSize(7);
  doc.setTextColor(150, 150, 150);
  // Left side - page number
  doc.text(
    `صفحة ${pageNum} من ${doc.internal.pages.length - 1}`,
    margin,
    pageHeight - 8,
    { align: 'left' }
  );
  // Center - divider line
  doc.setDrawColor(200, 200, 200);
  doc.setLineWidth(0.3);
  doc.line(margin, pageHeight - 12, pageWidth - margin, pageHeight - 12);
  // Right side - hospital name
  doc.text(
    hospitalName,
    pageWidth - margin,
    pageHeight - 8,
    { align: 'right' }
  );
};

/**
 * Draw a horizontal bar chart using jsPDF rect
 */
const drawBarChart = (
  doc: jsPDF,
  data: { label: string; value: number; color?: string }[],
  x: number,
  y: number,
  maxWidth: number,
  barHeight: number,
  maxValue: number,
  showLabel: boolean = true,
  chartWidth?: number
) => {
  try {
    const effectiveMaxValue = maxValue || Math.max(...data.map(d => d.value), 10);
    const cw = chartWidth || maxWidth * 0.55;
    const labelWidth = maxWidth - cw - 5;
    const gap = 2;

    data.forEach((item, i) => {
      const barY = y + i * (barHeight + gap);
      const barW = (item.value / effectiveMaxValue) * cw;
      const color = item.color || '#0d9488';

      if (showLabel) {
        doc.setFontSize(7);
        doc.setTextColor(60, 60, 60);
        doc.text(item.label, x, barY + barHeight / 2 + 1.5, { align: 'right' });
      }

      // Bar background
      doc.setFillColor(240, 240, 240);
      doc.rect(x + labelWidth, barY, cw, barHeight, 'F');

      // Bar fill
      const [r, g, b] = hexToRgb(color);
      doc.setFillColor(r, g, b);
      doc.rect(x + labelWidth, barY, Math.max(barW, 2), barHeight, 'F');

      // Value text
      doc.setFontSize(6);
      doc.setTextColor(100, 100, 100);
      doc.text(`${Math.round(item.value)}%`, x + labelWidth + barW + 2, barY + barHeight / 2 + 1.5);
    });
  } catch {
    // silently fail if drawing fails
  }
};

/**
 * Convert hex color to RGB tuple
 */
const hexToRgb = (hex: string): [number, number, number] => {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return result
    ? [parseInt(result[1], 16), parseInt(result[2], 16), parseInt(result[3], 16)]
    : [13, 148, 136];
};

/**
 * Export survey responses to PDF
 */
export const exportToPDF = async (
  responses: SurveyResponse[],
  stats: DashboardStats,
  title: string = 'تقرير استبيانات رضا المرضى',
  logoUrl?: string,
  hospitalName: string = 'MedSurvey Pro'
): Promise<boolean> => {
  try {
    const [{ default: JsPDF }, { autoTable }] = await Promise.all([
      import('jspdf'),
      import('jspdf-autotable'),
    ]);
    const doc = new JsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4',
    });

    doc.setFont('helvetica');
    
    // Defensive page dimension detection
    let pageWidth = 210;
    let pageHeight = 297;
    try {
      if (typeof doc.internal.pageSize.getWidth === 'function') {
        pageWidth = doc.internal.pageSize.getWidth();
        pageHeight = doc.internal.pageSize.getHeight();
      } else {
        const ps = doc.internal.pageSize as { width?: number; height?: number };
        pageWidth = ps.width || 210;
        pageHeight = ps.height || 297;
      }
    } catch {
      // use defaults
    }
    
    const margin = 15;
    let yPosition = margin;

    // Header with logo and title
    doc.setFillColor(13, 148, 136);
    doc.rect(0, 0, pageWidth, 45, 'F');
    
    // Add logo if available
    if (logoUrl) {
      try {
        doc.addImage(logoUrl, 'PNG', margin, 5, 30, 12);
      } catch {
        // Silently fall back if logo image fails
      }
    }
    
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.text(title, pageWidth / 2, 18, { align: 'center' });
    
    doc.setFontSize(8);
    doc.text(hospitalName, pageWidth / 2, 26, { align: 'center' });
    
    doc.setFontSize(9);
    doc.text(`تاريخ التقرير: ${formatDate(new Date())}`, pageWidth / 2, 36, { align: 'center' });

    yPosition = 55;

    // Stats Summary
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(14);
    doc.text('ملخص الإحصائيات', pageWidth - margin, yPosition, { align: 'right' });
    yPosition += 10;

    // Stats boxes
    const statsData = [
      ['إجمالي الاستجابات', stats.totalResponses.toString()],
      ['معدل الرضا العام', `${stats.averageScore}%`],
      ['مؤشر NPS (ولاء المراجعين)', stats.npsScore.toString()],
      ['نمو النشاط (المقارنة)', `${stats.responseRate}%`],
    ];

    autoTable(doc, {
      startY: yPosition,
      head: [['المؤشر', 'القيمة']],
      body: statsData,
      theme: 'grid',
      headStyles: {
        fillColor: [13, 148, 136],
        textColor: 255,
        halign: 'center',
        fontSize: 10,
      },
      bodyStyles: {
        halign: 'center',
        fontSize: 10,
      },
      columnStyles: {
        0: { cellWidth: 80 },
        1: { cellWidth: 40 },
      },
      margin: { left: margin, right: margin },
    });

    yPosition = doc.lastAutoTable.finalY + 15;

    // Satisfaction Distribution with Bar Chart
    doc.setFontSize(14);
    doc.text('توزيع مستوى الرضا', pageWidth - margin, yPosition, { align: 'right' });
    yPosition += 10;

    // Bar chart for distribution
    try {
      const satColors: Record<string, string> = {
        'ممتاز': '#10b981',
        'جيد': '#3b82f6',
        'متوسط': '#f59e0b',
        'ضعيف': '#ef4444',
        'Excellent': '#10b981',
        'Good': '#3b82f6',
        'Average': '#f59e0b',
        'Poor': '#ef4444',
      };

      if (stats.satisfactionDistribution.length > 0 && stats.totalResponses > 0) {
        const satData = stats.satisfactionDistribution.map(item => ({
          label: item.level,
          value: Math.round((item.count / stats.totalResponses) * 100),
          color: satColors[item.level] || item.color || '#0d9488',
        }));
        drawBarChart(doc, satData, margin + 5, yPosition, pageWidth - 2 * margin - 10, 8, 100, true, 60);
        yPosition += satData.length * 10 + 8;
      }
    } catch {
      // fallback: skip chart and continue
    }

    // Distribution table
    const satisfactionData = stats.satisfactionDistribution.map(item => [
      item.level,
      item.count.toString(),
      `${Math.round((item.count / stats.totalResponses) * 100)}%`,
    ]);

    autoTable(doc, {
      startY: yPosition,
      head: [['المستوى', 'العدد', 'النسبة']],
      body: satisfactionData,
      theme: 'grid',
      headStyles: {
        fillColor: [13, 148, 136],
        textColor: 255,
        halign: 'center',
        fontSize: 10,
      },
      bodyStyles: {
        halign: 'center',
        fontSize: 10,
      },
      margin: { left: margin, right: margin },
    });

    yPosition = doc.lastAutoTable.finalY + 15;

    // Department Scores with Bar Chart
    if (yPosition > pageHeight - 100) {
      doc.addPage();
      yPosition = margin;
    }

    doc.setFontSize(14);
    doc.text('الرضا حسب القسم', pageWidth - margin, yPosition, { align: 'right' });
    yPosition += 8;

    // Bar chart for departments (top 8 to fit)
    try {
      if (stats.departmentScores.length > 0) {
        const deptChartData = stats.departmentScores.slice(0, 8).map(dept => ({
          label: dept.name.length > 12 ? dept.name.substring(0, 11) + '..' : dept.name,
          value: dept.score,
          color: dept.score >= 85 ? '#10b981' : dept.score >= 70 ? '#3b82f6' : dept.score >= 50 ? '#f59e0b' : '#ef4444',
        }));
        drawBarChart(doc, deptChartData, margin + 5, yPosition, pageWidth - 2 * margin - 10, 7, 100, true, 65);
        yPosition += Math.min(deptChartData.length, 8) * 9 + 10;
      }
    } catch {
      // fallback: skip chart and continue
    }

    if (yPosition > pageHeight - 50) {
      doc.addPage();
      yPosition = margin;
    }

    const deptData = stats.departmentScores.map(dept => [
      dept.name,
      dept.count.toString(),
      `${dept.score}%`,
      getSatisfactionLevel(dept.score),
    ]);

    autoTable(doc, {
      startY: yPosition,
      head: [['القسم', 'عدد الاستجابات', 'معدل الرضا', 'المستوى']],
      body: deptData,
      theme: 'grid',
      headStyles: {
        fillColor: [13, 148, 136],
        textColor: 255,
        halign: 'center',
        fontSize: 9,
      },
      bodyStyles: {
        halign: 'center',
        fontSize: 9,
      },
      margin: { left: margin, right: margin },
    });

    yPosition = doc.lastAutoTable.finalY + 15;

    // Responses Table (New Page)
    doc.addPage();
    yPosition = margin;

    doc.setFontSize(14);
    doc.text('تفاصيل الاستجابات', pageWidth - margin, yPosition, { align: 'right' });
    yPosition += 10;

    const responsesData = responses.map((r, i) => [
      (i + 1).toString(),
      r.patientInfo.name || '—',
      r.patientInfo.phone || '—',
      r.department,
      r.patientInfo.gender,
      r.patientInfo.visitType,
      `${r.overallScore}%`,
      formatDate(r.submittedAt),
    ]);

    autoTable(doc, {
      startY: yPosition,
      head: [['#', 'الاسم', 'الهاتف', 'القسم', 'الجنس', 'نوع الزيارة', 'التقييم', 'التاريخ']],
      body: responsesData,
      theme: 'grid',
      headStyles: {
        fillColor: [13, 148, 136],
        textColor: 255,
        halign: 'center',
        fontSize: 8,
      },
      bodyStyles: {
        halign: 'center',
        fontSize: 8,
      },
      margin: { left: margin, right: margin },
    });

    // Professional footer on each page
    const totalPages = doc.internal.pages.length - 1;
    for (let i = 1; i <= totalPages; i++) {
      doc.setPage(i);
      drawFooter(doc, pageWidth, pageHeight, hospitalName, margin);
    }

    // Save the PDF
    const fileName = `survey-report-${new Date().toISOString().split('T')[0]}.pdf`;
    doc.save(fileName);

    return true;
  } catch (error) {
    logger.error('Error exporting to PDF:', error);
    if (error instanceof Error) {
      logger.error('Stack:', error.stack);
    }
    throw error;
  }
};

/**
 * Export survey responses to Excel
 */
export const exportToExcel = async (
  responses: SurveyResponse[],
  stats: DashboardStats,
  _title: string = 'تقرير استبيانات رضا المرضى'
): Promise<boolean> => {
  try {
    const [{ default: ExcelJS }, { saveAs }] = await Promise.all([
      import('exceljs'),
      import('file-saver'),
    ]);
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'MedSurvey Pro';
    workbook.created = new Date();

    const styleHeader = (row: Row) => {
      row.eachCell(cell => {
        cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0D9488' } };
        cell.alignment = { vertical: 'middle', horizontal: 'center' };
      });
    };

    const summarySheet = workbook.addWorksheet('ملخص التقرير', { views: [{ rightToLeft: true }] });
    summarySheet.columns = [{ width: 25 }, { width: 15 }, { width: 15 }];
    
    summarySheet.addRow(['تقرير استبيانات رضا المرضى']).font = { bold: true, size: 14 };
    summarySheet.addRow(['تاريخ التقرير', new Date().toLocaleDateString('ar-SA')]);
    summarySheet.addRow([]);
    summarySheet.addRow(['ملخص الإحصائيات']).font = { bold: true };
    styleHeader(summarySheet.addRow(['المؤشر', 'القيمة']));
    
    summarySheet.addRow(['إجمالي الاستجابات', stats.totalResponses]);
    summarySheet.addRow(['معدل الرضا العام', `${stats.averageScore}%`]);
    summarySheet.addRow(['مؤشر NPS (ولاء المراجعين)', stats.npsScore]);
    summarySheet.addRow(['نمو النشاط (المقارنة)', `${stats.responseRate}%`]);
    summarySheet.addRow([]);
    
    summarySheet.addRow(['توزيع مستوى الرضا']).font = { bold: true };
    styleHeader(summarySheet.addRow(['المستوى', 'العدد', 'النسبة']));
    
    stats.satisfactionDistribution.forEach(item => {
      summarySheet.addRow([
        item.level,
        item.count,
        `${Math.round((item.count / Math.max(1, stats.totalResponses)) * 100)}%`,
      ]);
    });

    const deptSheet = workbook.addWorksheet('الأقسام', { views: [{ rightToLeft: true }] });
    deptSheet.columns = [{ width: 25 }, { width: 15 }, { width: 15 }, { width: 15 }];
    
    deptSheet.addRow(['الرضا حسب القسم']).font = { bold: true, size: 14 };
    styleHeader(deptSheet.addRow(['القسم', 'عدد الاستجابات', 'معدل الرضا', 'المستوى']));
    
    stats.departmentScores.forEach(dept => {
      const level = dept.score >= 85 ? 'ممتاز' : dept.score >= 70 ? 'جيد' : dept.score >= 50 ? 'متوسط' : 'ضعيف';
      deptSheet.addRow([
        dept.name,
        dept.count,
        `${dept.score}%`,
        level,
      ]);
    });

    const catSheet = workbook.addWorksheet('الفئات', { views: [{ rightToLeft: true }] });
    catSheet.columns = [{ width: 25 }, { width: 15 }];
    
    catSheet.addRow(['الرضا حسب الفئة']).font = { bold: true, size: 14 };
    styleHeader(catSheet.addRow(['الفئة', 'معدل الرضا']));
    
    stats.categoryScores.forEach(cat => {
      catSheet.addRow([
        cat.category,
        `${cat.score}%`,
      ]);
    });

    const trendSheet = workbook.addWorksheet('الاتجاه', { views: [{ rightToLeft: true }] });
    trendSheet.columns = [{ width: 15 }, { width: 15 }, { width: 15 }];
    
    trendSheet.addRow(['اتجاه الرضا الأسبوعي']).font = { bold: true, size: 14 };
    styleHeader(trendSheet.addRow(['التاريخ', 'معدل الرضا', 'عدد الاستجابات']));
    
    stats.trendData.forEach(item => {
      trendSheet.addRow([
        item.date,
        `${item.score}%`,
        item.count,
      ]);
    });

    const resSheet = workbook.addWorksheet('الاستجابات', { views: [{ rightToLeft: true }] });
    resSheet.columns = [
      { width: 5 }, { width: 20 }, { width: 15 }, { width: 20 },
      { width: 10 }, { width: 15 }, { width: 15 }, { width: 12 },
      { width: 10 }, { width: 20 }
    ];
    
    resSheet.addRow(['جميع الاستجابات']).font = { bold: true, size: 14 };
    styleHeader(resSheet.addRow([
      '#', 'الاسم', 'رقم الهاتف', 'القسم', 'الجنس', 
      'الفئة العمرية', 'نوع الزيارة', 'التقييم العام', 'المستوى', 'تاريخ التقديم'
    ]));
    
    responses.forEach((r, i) => {
      const level = r.overallScore >= 85 ? 'ممتاز' : r.overallScore >= 70 ? 'جيد' : r.overallScore >= 50 ? 'متوسط' : 'ضعيف';
      resSheet.addRow([
        i + 1,
        r.patientInfo.name || '—',
        r.patientInfo.phone || '—',
        r.department,
        r.patientInfo.gender,
        r.patientInfo.ageGroup,
        r.patientInfo.visitType,
        `${r.overallScore}%`,
        level,
        new Date(r.submittedAt).toLocaleDateString('ar-SA')
      ]);
    });

    if (responses.length > 0) {
      const ansSheet = workbook.addWorksheet('تفاصيل الإجابات', { views: [{ rightToLeft: true }] });
      const keys = Object.keys(responses[0]?.answers || {});
      
      const dynamicCols = keys.map(() => ({ width: 15 }));
      ansSheet.columns = [{ width: 5 }, { width: 20 }, ...dynamicCols];
      
      ansSheet.addRow(['تفاصيل الإجابات']).font = { bold: true, size: 14 };
      styleHeader(ansSheet.addRow([
        '#', 'القسم', ...keys
      ]));
      
      responses.forEach((r, i) => {
        ansSheet.addRow([
          i + 1,
          r.department,
          ...Object.values(r.answers).map(v => 
            typeof v === 'boolean' ? (v ? 'نعم' : 'لا') :
            v === 'yes' ? 'نعم' : v === 'no' ? 'لا' : String(v)
          )
        ]);
      });
    }

    const fileName = `survey-report-${new Date().toISOString().split('T')[0]}.xlsx`;
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, fileName);

    return true;
  } catch (error) {
    logger.error('Error exporting to Excel:', error);
    return false;
  }
}



/**
 * Print PDF directly
 */
export const printPDF = (
  responses: SurveyResponse[],
  stats: DashboardStats,
  _title: string = 'تقرير استبيانات رضا المرضى',
  logoUrl?: string,
  hospitalName: string = 'MedSurvey Pro'
): void => {
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    alert('يرجى السماح بالنوافذ المنبثقة للطباعة');
    return;
  }


  const html = `
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
      <meta charset="UTF-8">
      <title>${escapeHtml(_title)}</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
          font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
          padding: 20px;
          color: #1e293b;
          direction: rtl;
          font-size: 12px;
        }
        .header {
          background: linear-gradient(135deg, #0d9488, #10b981);
          color: white;
          padding: 25px 30px;
          margin-bottom: 25px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 15px;
        }
        .header-logo { height: 45px; width: auto; }
        .header-text { text-align: center; }
        .header h1 { font-size: 22px; margin-bottom: 4px; font-weight: 700; }
        .header p { font-size: 12px; opacity: 0.9; }
        .section { margin-bottom: 25px; }
        .section h2 {
          font-size: 16px; color: #0d9488; margin-bottom: 12px;
          padding-bottom: 8px; border-bottom: 2px solid #0d9488; font-weight: 700;
        }
        .stats-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 12px;
          margin-bottom: 25px;
        }
        .stat-card {
          background: #f8fafc;
          padding: 16px;
          border-radius: 10px;
          text-align: center;
          border: 1px solid #e2e8f0;
        }
        .stat-card .value { font-size: 24px; font-weight: 700; color: #0d9488; }
        .stat-card .label { font-size: 11px; color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 8px 10px; text-align: center; border: 1px solid #e2e8f0; }
        th { background: #0d9488; color: white; font-weight: 700; font-size: 11px; }
        td { font-size: 11px; }
        tr:nth-child(even) { background: #f8fafc; }
        .bar-chart { margin-top: 10px; }
        .bar-row { display: flex; align-items: center; margin-bottom: 6px; gap: 8px; }
        .bar-label { width: 70px; font-size: 11px; color: #475569; text-align: left; }
        .bar-track { flex: 1; height: 18px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 4px; display: flex; align-items: center; padding-right: 6px; font-size: 9px; color: white; font-weight: 600; }
        .bar-value { width: 40px; font-size: 11px; color: #64748b; text-align: right; }
        .footer {
          margin-top: 25px; text-align: center; color: #94a3b8;
          font-size: 10px; padding-top: 12px; border-top: 1px solid #e2e8f0;
        }
        @media print {
          body { padding: 0; }
          .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          .bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          .stat-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; break-inside: avoid; }
          .section { break-inside: avoid; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        ${logoUrl ? `<img src="${escapeHtml(logoUrl)}" alt="شعار" class="header-logo" />` : ''}
        <div class="header-text">
          <h1>${escapeHtml(_title)}</h1>
          <p>${escapeHtml(hospitalName)} — ${formatDateTime(new Date())}</p>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="value">${stats.totalResponses}</div>
          <div class="label">إجمالي الاستجابات</div>
        </div>
        <div class="stat-card">
          <div class="value">${stats.averageScore}%</div>
          <div class="label">معدل الرضا العام</div>
        </div>
        <div class="stat-card">
          <div class="value">${stats.npsScore}</div>
          <div class="label">مؤشر NPS (ولاء المراجعين)</div>
        </div>
        <div class="stat-card">
          <div class="value">${stats.responseRate}%</div>
          <div class="label">نمو النشاط (المقارنة)</div>
        </div>
      </div>

      <div class="section">
        <h2>توزيع مستوى الرضا</h2>
        <div class="bar-chart">
          ${stats.satisfactionDistribution.map(item => {
            const colors: Record<string, string> = { 'ممتاز': '#10b981', 'جيد': '#3b82f6', 'متوسط': '#f59e0b', 'ضعيف': '#ef4444' };
            const pct = Math.round((item.count / stats.totalResponses) * 100);
            return `
              <div class="bar-row">
                <div class="bar-label">${escapeHtml(item.level)}</div>
                <div class="bar-track">
                  <div class="bar-fill" style="width:${pct}%;background:${colors[item.level] || '#0d9488'}">${pct}%</div>
                </div>
                <div class="bar-value">${item.count}</div>
              </div>
            `;
          }).join('')}
        </div>
      </div>

      <div class="section">
        <h2>الرضا حسب القسم</h2>
        <table>
          <thead>
            <tr>
              <th>القسم</th>
              <th>عدد الاستجابات</th>
              <th>معدل الرضا</th>
              <th>المستوى</th>
            </tr>
          </thead>
          <tbody>
            ${stats.departmentScores.map(dept => {
              const level = getSatisfactionLevel(dept.score);
              const lvlColors: Record<string, string> = { 'ممتاز': '#10b981', 'جيد': '#3b82f6', 'متوسط': '#f59e0b', 'ضعيف': '#ef4444' };
              return `
                <tr>
                  <td>${escapeHtml(dept.name)}</td>
                  <td>${dept.count}</td>
                  <td style="color:${lvlColors[level] || '#0d9488'};font-weight:700">${dept.score}%</td>
                  <td>${escapeHtml(level)}</td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>

      <div class="section">
        <h2>تفاصيل الاستجابات (${responses.length})</h2>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>الاسم</th>
              <th>الهاتف</th>
              <th>القسم</th>
              <th>الجنس</th>
              <th>نوع الزيارة</th>
              <th>التقييم</th>
              <th>التاريخ</th>
            </tr>
          </thead>
          <tbody>
            ${responses.map((r, i) => `
              <tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(r.patientInfo.name) || '—'}</td>
                <td dir="ltr">${escapeHtml(r.patientInfo.phone) || '—'}</td>
                <td>${escapeHtml(r.department)}</td>
                <td>${escapeHtml(r.patientInfo.gender)}</td>
                <td>${escapeHtml(r.patientInfo.visitType)}</td>
                <td>${r.overallScore}%</td>
                <td>${formatDate(r.submittedAt)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>

      <div class="footer">
        <p>${escapeHtml(hospitalName)} — نظام استبيانات رضا المرضى</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة</p>
      </div>

      <script>
        window.onload = function() { window.print(); };
      </script>
    </body>
    </html>
  `;

  printWindow.document.write(html);
  printWindow.document.close();
};

/**
 * Export to CSV (simple format)
 */
export const exportToCSV = (
  responses: SurveyResponse[],
  fileName: string = 'survey-responses'
): boolean => {
  try {
    const headers = [
      'رقم الاستجابة',
      'الاسم',
      'رقم الهاتف',
      'القسم',
      'الجنس',
      'الفئة العمرية',
      'نوع الزيارة',
      'التقييم العام',
      'تاريخ التقديم',
    ];

    const rows = responses.map(r => [
      r.id,
      r.patientInfo.name || '',
      r.patientInfo.phone || '',
      r.department,
      r.patientInfo.gender,
      r.patientInfo.ageGroup,
      r.patientInfo.visitType,
      `${r.overallScore}%`,
      formatDateTime(r.submittedAt),
    ]);

    const csvContent = [
      headers.join(','),
      ...rows.map(row => row.map(cell => `"${cell}"`).join(',')),
    ].join('\n');

    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${fileName}-${new Date().toISOString().split('T')[0]}.csv`;
    link.click();

    return true;
  } catch (error) {
    logger.error('Error exporting to CSV:', error);
    return false;
  }
};
