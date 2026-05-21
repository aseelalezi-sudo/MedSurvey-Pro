const fs = require('fs');

let content = fs.readFileSync('src/utils/exportUtils.ts', 'utf8');

// 1. Replace imports
content = content.replace(
  "import * as XLSX from 'xlsx';",
  "import ExcelJS from 'exceljs';\nimport { saveAs } from 'file-saver';"
);

// 2. Replace exportToExcel function entirely
const startMarker = `export const exportToExcel = (`;
const startIndex = content.indexOf(startMarker);

// Find the end of exportToExcel by looking for its closing bracket before printPDF
const endMarker = `export const printPDF =`;
const nextFuncIndex = content.indexOf(endMarker);

if (startIndex === -1 || nextFuncIndex === -1) {
  console.error("Could not find boundaries for exportToExcel");
  process.exit(1);
}

// Extract the content up to startIndex, and from the end of the function
const prefix = content.substring(0, startIndex);

// Find the last "};\n" before nextFuncIndex
let endIndex = content.lastIndexOf("};", nextFuncIndex);
if (endIndex === -1) endIndex = nextFuncIndex;
else endIndex += 3; // include "};\n"

const suffix = content.substring(endIndex);

const newFunction = `export const exportToExcel = async (
  responses: SurveyResponse[],
  stats: DashboardStats,
  _title: string = 'تقرير استبيانات رضا المرضى'
): Promise<boolean> => {
  try {
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'MedSurvey Pro';
    workbook.created = new Date();

    const styleHeader = (row: ExcelJS.Row) => {
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
    summarySheet.addRow(['معدل الرضا العام', \`\${stats.averageScore}%\`]);
    summarySheet.addRow(['مؤشر NPS (ولاء المراجعين)', stats.npsScore]);
    summarySheet.addRow(['نمو النشاط (المقارنة)', \`\${stats.responseRate}%\`]);
    summarySheet.addRow([]);
    
    summarySheet.addRow(['توزيع مستوى الرضا']).font = { bold: true };
    styleHeader(summarySheet.addRow(['المستوى', 'العدد', 'النسبة']));
    
    stats.satisfactionDistribution.forEach(item => {
      summarySheet.addRow([
        item.level,
        item.count,
        \`\${Math.round((item.count / Math.max(1, stats.totalResponses)) * 100)}%\`,
      ]);
    });

    const deptSheet = workbook.addWorksheet('الأقسام', { views: [{ rightToLeft: true }] });
    deptSheet.columns = [{ width: 25 }, { width: 15 }, { width: 15 }, { width: 15 }];
    
    deptSheet.addRow(['الرضا حسب القسم']).font = { bold: true, size: 14 };
    styleHeader(deptSheet.addRow(['القسم', 'عدد الاستجابات', 'معدل الرضا', 'المستوى']));
    
    stats.departmentScores.forEach(dept => {
      let level = dept.score >= 85 ? 'ممتاز' : dept.score >= 70 ? 'جيد' : dept.score >= 50 ? 'متوسط' : 'ضعيف';
      deptSheet.addRow([
        dept.name,
        dept.count,
        \`\${dept.score}%\`,
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
        \`\${cat.score}%\`,
      ]);
    });

    const trendSheet = workbook.addWorksheet('الاتجاه', { views: [{ rightToLeft: true }] });
    trendSheet.columns = [{ width: 15 }, { width: 15 }, { width: 15 }];
    
    trendSheet.addRow(['اتجاه الرضا الأسبوعي']).font = { bold: true, size: 14 };
    styleHeader(trendSheet.addRow(['التاريخ', 'معدل الرضا', 'عدد الاستجابات']));
    
    stats.trendData.forEach(item => {
      trendSheet.addRow([
        item.date,
        \`\${item.score}%\`,
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
      let level = r.overallScore >= 85 ? 'ممتاز' : r.overallScore >= 70 ? 'جيد' : r.overallScore >= 50 ? 'متوسط' : 'ضعيف';
      resSheet.addRow([
        i + 1,
        r.patientInfo.name || '—',
        r.patientInfo.phone || '—',
        r.department,
        r.patientInfo.gender,
        r.patientInfo.ageGroup,
        r.patientInfo.visitType,
        \`\${r.overallScore}%\`,
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

    const fileName = \`survey-report-\${new Date().toISOString().split('T')[0]}.xlsx\`;
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, fileName);

    return true;
  } catch (error) {
    logger.error('Error exporting to Excel:', error);
    return false;
  }
}
`;

fs.writeFileSync('src/utils/exportUtils.ts', prefix + newFunction + "\n\n" + suffix, 'utf8');
console.log('exportUtils.ts refactored successfully.');
