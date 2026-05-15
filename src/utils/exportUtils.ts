import jsPDF from 'jspdf';
import 'jspdf-autotable';
import type { UserOptions } from 'jspdf-autotable';
import * as XLSX from 'xlsx';
import { SurveyResponse, DashboardStats } from '../types';
import { createLogger } from './logger';

const logger = createLogger('exportUtils');


// Extend jsPDF type for autotable
declare module 'jspdf' {
  interface jsPDF {
    autoTable: (options: UserOptions) => jsPDF;
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
 * Export survey responses to PDF
 */
export const exportToPDF = (
  responses: SurveyResponse[],
  stats: DashboardStats,
  title: string = 'تقرير استبيانات رضا المرضى'
): boolean => {
  try {
    const doc = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4',
    });

    // Add Arabic font support - using default for now
    doc.setFont('helvetica');
    
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 15;
    let yPosition = margin;

    // Header
    doc.setFillColor(13, 148, 136); // Teal color
    doc.rect(0, 0, pageWidth, 40, 'F');
    
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.text(title, pageWidth / 2, 20, { align: 'center' });
    
    doc.setFontSize(10);
    doc.text(`تاريخ التقرير: ${formatDate(new Date())}`, pageWidth / 2, 32, { align: 'center' });

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

    doc.autoTable({
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

    // Satisfaction Distribution
    doc.setFontSize(14);
    doc.text('توزيع مستوى الرضا', pageWidth - margin, yPosition, { align: 'right' });
    yPosition += 10;

    const satisfactionData = stats.satisfactionDistribution.map(item => [
      item.level,
      item.count.toString(),
      `${Math.round((item.count / stats.totalResponses) * 100)}%`,
    ]);

    doc.autoTable({
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

    // Department Scores
    if (yPosition > pageHeight - 60) {
      doc.addPage();
      yPosition = margin;
    }

    doc.setFontSize(14);
    doc.text('الرضا حسب القسم', pageWidth - margin, yPosition, { align: 'right' });
    yPosition += 10;

    const deptData = stats.departmentScores.map(dept => [
      dept.name,
      dept.count.toString(),
      `${dept.score}%`,
      getSatisfactionLevel(dept.score),
    ]);

    doc.autoTable({
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

    doc.autoTable({
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

    // Footer on each page
    const totalPages = doc.internal.pages.length - 1;
    for (let i = 1; i <= totalPages; i++) {
      doc.setPage(i);
      doc.setFontSize(8);
      doc.setTextColor(128, 128, 128);
      doc.text(
        `صفحة ${i} من ${totalPages}`,
        pageWidth / 2,
        pageHeight - 10,
        { align: 'center' }
      );
      doc.text(
        'MedSurvey Pro - نظام استبيانات رضا المرضى',
        pageWidth - margin,
        pageHeight - 10,
        { align: 'right' }
      );
    }

    // Save the PDF
    const fileName = `survey-report-${new Date().toISOString().split('T')[0]}.pdf`;
    doc.save(fileName);

    return true;
  } catch (error) {
    logger.error('Error exporting to PDF:', error);
    return false;
  }
};

/**
 * Export survey responses to Excel
 */
export const exportToExcel = (
  responses: SurveyResponse[],
  stats: DashboardStats,
  _title: string = 'تقرير استبيانات رضا المرضى'
): boolean => {
  try {
    const workbook = XLSX.utils.book_new();

    // Sheet 1: Summary Statistics
    const summaryData = [
      ['تقرير استبيانات رضا المرضى'],
      ['تاريخ التقرير', formatDateTime(new Date())],
      [''],
      ['ملخص الإحصائيات'],
      ['المؤشر', 'القيمة'],
      ['إجمالي الاستجابات', stats.totalResponses],
      ['معدل الرضا العام', `${stats.averageScore}%`],
      ['مؤشر NPS (ولاء المراجعين)', stats.npsScore],
      ['نمو النشاط (المقارنة)', `${stats.responseRate}%`],
      [''],
      ['توزيع مستوى الرضا'],
      ['المستوى', 'العدد', 'النسبة'],
      ...stats.satisfactionDistribution.map(item => [
        item.level,
        item.count,
        `${Math.round((item.count / stats.totalResponses) * 100)}%`,
      ]),
    ];

    const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
    
    // Set column widths
    summarySheet['!cols'] = [
      { wch: 25 },
      { wch: 15 },
      { wch: 15 },
    ];

    XLSX.utils.book_append_sheet(workbook, summarySheet, 'ملخص التقرير');

    // Sheet 2: Department Scores
    const deptData = [
      ['الرضا حسب القسم'],
      ['القسم', 'عدد الاستجابات', 'معدل الرضا', 'المستوى'],
      ...stats.departmentScores.map(dept => [
        dept.name,
        dept.count,
        `${dept.score}%`,
        getSatisfactionLevel(dept.score),
      ]),
    ];

    const deptSheet = XLSX.utils.aoa_to_sheet(deptData);
    deptSheet['!cols'] = [
      { wch: 25 },
      { wch: 15 },
      { wch: 15 },
      { wch: 15 },
    ];

    XLSX.utils.book_append_sheet(workbook, deptSheet, 'الأقسام');

    // Sheet 3: Category Scores
    const categoryData = [
      ['الرضا حسب الفئة'],
      ['الفئة', 'معدل الرضا'],
      ...stats.categoryScores.map(cat => [
        cat.category,
        `${cat.score}%`,
      ]),
    ];

    const categorySheet = XLSX.utils.aoa_to_sheet(categoryData);
    categorySheet['!cols'] = [
      { wch: 25 },
      { wch: 15 },
    ];

    XLSX.utils.book_append_sheet(workbook, categorySheet, 'الفئات');

    // Sheet 4: Trend Data
    const trendData = [
      ['اتجاه الرضا الأسبوعي'],
      ['التاريخ', 'معدل الرضا', 'عدد الاستجابات'],
      ...stats.trendData.map(item => [
        item.date,
        `${item.score}%`,
        item.count,
      ]),
    ];

    const trendSheet = XLSX.utils.aoa_to_sheet(trendData);
    trendSheet['!cols'] = [
      { wch: 15 },
      { wch: 15 },
      { wch: 15 },
    ];

    XLSX.utils.book_append_sheet(workbook, trendSheet, 'الاتجاه');

    // Sheet 5: All Responses
    const responsesHeader = [
      '#',
      'الاسم',
      'رقم الهاتف',
      'القسم',
      'الجنس',
      'الفئة العمرية',
      'نوع الزيارة',
      'التقييم العام',
      'المستوى',
      'تاريخ التقديم',
    ];

    const responsesData = responses.map((r, i) => [
      i + 1,
      r.patientInfo.name || '—',
      r.patientInfo.phone || '—',
      r.department,
      r.patientInfo.gender,
      r.patientInfo.ageGroup,
      r.patientInfo.visitType,
      `${r.overallScore}%`,
      getSatisfactionLevel(r.overallScore),
      formatDateTime(r.submittedAt),
    ]);

    const responsesSheetData = [
      ['جميع الاستجابات'],
      responsesHeader,
      ...responsesData,
    ];

    const responsesSheet = XLSX.utils.aoa_to_sheet(responsesSheetData);
    responsesSheet['!cols'] = [
      { wch: 5 },
      { wch: 20 },
      { wch: 12 },
      { wch: 20 },
      { wch: 10 },
      { wch: 15 },
      { wch: 15 },
      { wch: 12 },
      { wch: 10 },
      { wch: 20 },
    ];

    XLSX.utils.book_append_sheet(workbook, responsesSheet, 'الاستجابات');

    // Sheet 6: Detailed Answers
    const answersHeader = [
      '#',
      'القسم',
      ...Object.keys(responses[0]?.answers || {}).map(k => k),
    ];

    const answersData = responses.map((r, i) => [
      i + 1,
      r.department,
      ...Object.values(r.answers).map(v => 
        typeof v === 'boolean' ? (v ? 'نعم' : 'لا') :
        v === 'yes' ? 'نعم' : v === 'no' ? 'لا' : String(v)
      ),
    ]);

    const answersSheetData = [
      ['تفاصيل الإجابات'],
      answersHeader,
      ...answersData,
    ];

    const answersSheet = XLSX.utils.aoa_to_sheet(answersSheetData);
    XLSX.utils.book_append_sheet(workbook, answersSheet, 'تفاصيل الإجابات');

    // Generate filename and save
    const fileName = `survey-report-${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(workbook, fileName);

    return true;
  } catch (error) {
    logger.error('Error exporting to Excel:', error);
    return false;
  }
};

/**
 * Print PDF directly
 */
export const printPDF = (
  responses: SurveyResponse[],
  stats: DashboardStats,
  _title: string = 'تقرير استبيانات رضا المرضى'
): void => {
  // Create a printable HTML document
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
      <title>${_title}</title>
      <style>
        * {
          box-sizing: border-box;
          margin: 0;
          padding: 0;
        }
        body {
          font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
          padding: 20px;
          color: #333;
          direction: rtl;
        }
        .header {
          background: linear-gradient(135deg, #0d9488, #10b981);
          color: white;
          padding: 30px;
          text-align: center;
          margin-bottom: 30px;
          border-radius: 10px;
        }
        .header h1 {
          font-size: 24px;
          margin-bottom: 10px;
        }
        .header p {
          font-size: 14px;
          opacity: 0.9;
        }
        .section {
          margin-bottom: 30px;
        }
        .section h2 {
          font-size: 18px;
          color: #0d9488;
          margin-bottom: 15px;
          padding-bottom: 10px;
          border-bottom: 2px solid #0d9488;
        }
        .stats-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 15px;
          margin-bottom: 30px;
        }
        .stat-card {
          background: #f8fafc;
          padding: 20px;
          border-radius: 10px;
          text-align: center;
          border: 1px solid #e2e8f0;
        }
        .stat-card .value {
          font-size: 28px;
          font-weight: bold;
          color: #0d9488;
        }
        .stat-card .label {
          font-size: 12px;
          color: #64748b;
          margin-top: 5px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 10px;
        }
        th, td {
          padding: 12px;
          text-align: center;
          border: 1px solid #e2e8f0;
        }
        th {
          background: #0d9488;
          color: white;
          font-weight: bold;
        }
        tr:nth-child(even) {
          background: #f8fafc;
        }
        .footer {
          margin-top: 30px;
          text-align: center;
          color: #64748b;
          font-size: 12px;
          padding-top: 20px;
          border-top: 1px solid #e2e8f0;
        }
        @media print {
          body { padding: 0; }
          .header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>${_title}</h1>
        <p>تاريخ التقرير: ${formatDateTime(new Date())}</p>
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
        <table>
          <thead>
            <tr>
              <th>المستوى</th>
              <th>العدد</th>
              <th>النسبة</th>
            </tr>
          </thead>
          <tbody>
            ${stats.satisfactionDistribution.map(item => `
              <tr>
                <td>${item.level}</td>
                <td>${item.count}</td>
                <td>${Math.round((item.count / stats.totalResponses) * 100)}%</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
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
            ${stats.departmentScores.map(dept => `
              <tr>
                <td>${dept.name}</td>
                <td>${dept.count}</td>
                <td>${dept.score}%</td>
                <td>${getSatisfactionLevel(dept.score)}</td>
              </tr>
            `).join('')}
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
                <td>${r.patientInfo.name || '—'}</td>
                <td dir="ltr">${r.patientInfo.phone || '—'}</td>
                <td>${r.department}</td>
                <td>${r.patientInfo.gender}</td>
                <td>${r.patientInfo.visitType}</td>
                <td>${r.overallScore}%</td>
                <td>${formatDate(r.submittedAt)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>

      <div class="footer">
        <p>MedSurvey Pro - نظام استبيانات رضا المرضى</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة</p>
      </div>

      <script>
        window.onload = function() {
          window.print();
        };
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
