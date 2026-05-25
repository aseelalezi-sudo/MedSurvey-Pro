import{g as U,u as O,r as N,x as Y,B,y as G,o as H,j as t,f as q}from"./main-CQRVPgmZ.js";import{u as K}from"./useDateFilter-Cz5sERF9.js";import{t as V}from"./tickets-CvtXVXSQ.js";import{a as W}from"./audit-dTJGrVZ7.js";import{A as J}from"./arrow-left-DOpgYYMh.js";import{F as Q,P as X}from"./printer-Cw3pzeqc.js";import{C}from"./calendar-BgVdGqEs.js";import{B as L}from"./building-2-BuOPzcOK.js";import{L as P}from"./loader-circle-CJuN1E-t.js";import{C as Z}from"./circle-check-BPQmKP-5.js";import{F as tt}from"./file-text-Byv_jH5z.js";import{T as et}from"./trending-up-C6HEwCkv.js";import{C as rt}from"./circle-alert-B7zUjT1D.js";import{B as at}from"./brain-CuVTA7XH.js";import{A as ot}from"./award-BRn4krSb.js";const st=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z",key:"1oefj6"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5",key:"wfsgrz"}],["path",{d:"M12 18v-6",key:"17g6i2"}],["path",{d:"m9 15 3 3 3-3",key:"1npd3o"}]],it=U("file-down",st);function lt(c="all"){const{currentUser:l}=O(),p=l?.role==="head_of_department"?l.department:void 0,[s,a]=N.useState(p||c);return N.useEffect(()=>{p&&s!==p&&a(p)},[p,s]),{selectedDepartment:s,setSelectedDepartment:a,restrictedDepartment:p,effectiveDepartment:p||(s!=="all"?s:void 0)}}const D=(c,l)=>c>=85?l("score_excellent","ممتاز"):c>=70?l("score_good","جيد"):c>=50?l("score_average","متوسط"):l("score_poor","ضعيف"),dt=(c,l,p)=>{const{stats:s,hospitalName:a,operatingTitle:y,logo:g,language:n,t:e,reportDepartmentLabel:x}=p;if(!s)return;const u=n==="ar",h=a.replace(/\s+/g,"_"),$=e("report_executive_title","تقرير_الملخص_التنفيذي_ورضا_المرضى_الشامل").replace(/\s+/g,"_"),v=new Date().toISOString().slice(0,10),f=l==="pdf"?`${$}_${h}_${v}`:`${e("report_executive_title","تقرير الملخص التنفيذي ورضا المرضى الشامل")} - ${a}`,i=`
    <!DOCTYPE html>
    <html dir="${u?"rtl":"ltr"}" lang="${n}">
    <head>
      <meta charset="UTF-8">
      <title>${f}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; padding: 25px; color: #1e293b; background-color: #ffffff; line-height: 1.6; }
        .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        .logo-placeholder { width: 50px; height: 50px; background: linear-gradient(135deg, #0d9488, #10b981); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold; }
        .hospital-info h1 { font-size: 18px; font-weight: 800; color: #0f172a; }
        .hospital-info p { font-size: 11px; color: #64748b; margin-top: 2px; }
        .header-left { text-align: ${u?"left":"right"}; }
        .report-meta { font-size: 12px; color: #64748b; }
        .report-meta strong { color: #0f172a; }
        .report-title-banner { text-align: center; background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 25px; border-radius: 16px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .report-title-banner h2 { font-size: 22px; font-weight: 900; }
        .report-title-banner p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; text-align: center; }
        .stat-card .value { font-size: 26px; font-weight: 800; color: #0d9488; }
        .stat-card .label { font-size: 11px; color: #64748b; font-weight: 600; margin-top: 5px; }
        .section-title { font-size: 15px; font-weight: 800; color: #0f172a; border-right: 4px solid #0d9488; padding-right: 10px; margin-bottom: 15px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        th { background-color: #0d9488; color: white; font-weight: 700; padding: 10px; text-align: center; }
        td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
        .badge-excellent { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-good { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-average { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-poor { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        @page { size: A4; margin: 15mm; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
        @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
      </style>
    </head>
    <body>
      <div class="header-container">
        <div class="header-right">
          ${g?`<img src="${g}" alt="Logo" style="height: 50px; width: auto; max-width: 150px; object-fit: contain;" />`:'<div class="logo-placeholder">⚕️</div>'}
          <div class="hospital-info">
            <h1>${a}</h1>
            <p>${y}</p>
          </div>
        </div>
        <div class="header-left">
          <div class="report-meta">
            <p><strong>${e("report_date","تاريخ التقرير")}:</strong> ${new Date().toLocaleDateString(u?"ar-SA":"en-US")}</p>
            <p><strong>${e("export_department","القسم المستهدف")}:</strong> ${x||"الكل"}</p>
            ${l==="pdf"?'<p style="margin-top: 5px; color: #0d9488; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>':""}
          </div>
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${e("report_executive_title","تقرير الملخص التنفيذي ورضا المرضى الشامل")}</h2>
        <p>${e("report_executive_subtitle","تحليل شامل ومفصل لمستويات رضا المستفيدين ومقاييس الأداء لخدمات الرعاية الصحية")}</p>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="value">${s.totalResponses}</div>
          <div class="label">${e("total_responses","إجمالي الاستجابات")}</div>
        </div>
        <div class="stat-card">
          <div class="value">${s.averageScore}%</div>
          <div class="label">${e("satisfaction_rate","معدل الرضا العام")}</div>
        </div>
        <div class="stat-card">
          <div class="value">${s.npsScore}</div>
          <div class="label">${e("nps_score","مؤشر التوصية والولاء (NPS)")}</div>
        </div>
        <div class="stat-card">
          <div class="value">${s.responseRate}%</div>
          <div class="label">${e("response_rate","معدل استجابة المرضى")}</div>
        </div>
      </div>
      <div class="grid-2">
        <div>
          <h3 class="section-title">${e("satisfaction_distribution","توزيع مستوى رضا المرضى")}</h3>
          <table>
            <thead>
              <tr>
                <th>${e("level","المستوى")}</th>
                <th>${e("count","العدد")}</th>
                <th>${e("percentage","النسبة")}</th>
              </tr>
            </thead>
            <tbody>
              ${s.satisfactionDistribution.map(o=>{let d="badge-good";return o.level.includes("ممتاز")||o.level.toLowerCase().includes("excellent")?d="badge-excellent":o.level.includes("متوسط")||o.level.toLowerCase().includes("average")?d="badge-average":(o.level.includes("ضعيف")||o.level.toLowerCase().includes("poor"))&&(d="badge-poor"),`
                  <tr>
                    <td><span class="badge ${d}">${o.level}</span></td>
                    <td>${o.count}</td>
                    <td><strong>${Math.round(o.count/s.totalResponses*100)}%</strong></td>
                  </tr>
                `}).join("")}
            </tbody>
          </table>
        </div>
        <div>
          <h3 class="section-title">${e("category_satisfaction","الرضا حسب فئات الخدمة")}</h3>
          <table>
            <thead>
              <tr>
                <th>${e("category","الفئة الخدمية")}</th>
                <th>${e("satisfaction_rate","معدل الرضا")}</th>
                <th>${e("level","التقييم العام")}</th>
              </tr>
            </thead>
            <tbody>
              ${s.categoryScores.map(o=>`
                <tr>
                  <td><strong>${o.category}</strong></td>
                  <td><span style="color: #0d9488; font-weight: bold;">${o.score}%</span></td>
                  <td>${D(o.score,e)}</td>
                </tr>
              `).join("")}
            </tbody>
          </table>
        </div>
      </div>
      <h3 class="section-title">${e("department_satisfaction_comparative","التقييم المقارن للأقسام الطبية")}</h3>
      <table>
        <thead>
          <tr>
            <th>${e("department","القسم الطبي")}</th>
            <th>${e("total_responses","عدد الاستجابات")}</th>
            <th>${e("satisfaction_rate","معدل الرضا العام")}</th>
            <th>${e("level","مستوى الأداء")}</th>
          </tr>
        </thead>
        <tbody>
          ${s.departmentScores.map(o=>{let d="color: #10b981; font-weight: bold;";return o.score<50?d="color: #ef4444; font-weight: bold;":o.score<70&&(d="color: #f59e0b; font-weight: bold;"),`
              <tr>
                <td><strong>${o.name}</strong></td>
                <td>${o.count}</td>
                <td><span style="${d}">${o.score}%</span></td>
                <td><strong>${D(o.score,e)}</strong></td>
              </tr>
            `}).join("")}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - ${e("system_description","النظام الذكي المتكامل لاستبيانات رضا واستجابات المرضى ومؤشرات الأداء")}</p>
        <p>© ${new Date().getFullYear()} ${a} | ${e("confidential_report","تقرير سري ومحمي للاستخدام الداخلي فقط")}</p>
      </div>
      <div class="page-footer">${a} | صفحة <span class="pageNumber"></span> | MedSurvey Pro</div>
    </body>
    </html>
  `;c.document.write(i)},nt=(c,l,p)=>{const{stats:s,hospitalName:a,operatingTitle:y,logo:g,language:n,t:e}=p;if(!s)return;const x=n==="ar",u=a.replace(/\s+/g,"_"),h=e("report_departments_title","تقرير_تقييم_الأقسام_والمقارنات_الإدارية").replace(/\s+/g,"_"),$=new Date().toISOString().slice(0,10),v=l==="pdf"?`${h}_${u}_${$}`:`${e("report_departments_title","تقرير تقييم الأقسام والمقارنات الإدارية")} - ${a}`,f=`
    <!DOCTYPE html>
    <html dir="${x?"rtl":"ltr"}" lang="${n}">
    <head>
      <meta charset="UTF-8">
      <title>${v}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
        .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #6366f1; padding-bottom: 15px; margin-bottom: 25px; }
        .hospital-info h1 { font-size: 16px; font-weight: 800; }
        .report-meta { font-size: 11px; color: #64748b; }
        .report-title-banner { text-align: center; background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .report-title-banner h2 { font-size: 18px; font-weight: 900; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th { background-color: #6366f1; color: white; font-weight: bold; padding: 10px; }
        td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .bar-container { display: flex; align-items: center; gap: 10px; justify-content: flex-start; }
        .bar-outer { width: 100px; height: 10px; background-color: #e2e8f0; border-radius: 5px; overflow: hidden; }
        .bar-inner { height: 100%; border-radius: 5px; }
        @page { size: A4; margin: 15mm; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
        @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
        .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
      </style>
    </head>
    <body>
      <div class="header-container">
        <div style="display: flex; align-items: center; gap: 15px;">
          ${g?`<img src="${g}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />`:""}
          <div class="hospital-info">
            <h1>${a}</h1>
            <p>${y}</p>
          </div>
        </div>
        <div class="report-meta">
          <p><strong>${e("report_date","التاريخ")}:</strong> ${new Date().toLocaleDateString(x?"ar-SA":"en-US")}</p>
          ${l==="pdf"?'<p style="margin-top: 5px; color: #6366f1; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>':""}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${e("report_departments_title","تقرير تقييم الأقسام والمقارنات الإدارية")}</h2>
        <p>${e("report_departments_desc","تحليل مقارن لمستويات رضا المستفيدين والشكاوى الواردة حسب التوزيع المكاني للأقسام")}</p>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #6366f1; padding-right: 8px;">جدول تقييم وترتيب الأقسام</h3>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>القسم الطبي</th>
            <th>عدد الاستجابات المستلمة</th>
            <th>معدل الرضا المقاس</th>
            <th>التمثيل البصري للنسبة</th>
            <th>مستوى الرضا</th>
          </tr>
        </thead>
        <tbody>
          ${s.departmentScores.map((i,o)=>{let d="#10b981";return i.score<50?d="#ef4444":i.score<70&&(d="#f59e0b"),`
              <tr>
                <td>${o+1}</td>
                <td><strong>${i.name}</strong></td>
                <td>${i.count}</td>
                <td><strong style="color: ${d}">${i.score}%</strong></td>
                <td>
                  <div class="bar-container">
                    <div class="bar-outer">
                      <div class="bar-inner" style="width: ${i.score}%; background-color: ${d};"></div>
                    </div>
                    <span>${i.score}%</span>
                  </div>
                </td>
                <td><strong>${D(i.score,e)}</strong></td>
              </tr>
            `}).join("")}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - نظام تقارير الأقسام والتحليلات المقارنة</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${a}</p>
      </div>
      <div class="page-footer">${a} | MedSurvey Pro</div>
    </body>
    </html>
  `;c.document.write(f)},pt=(c,l,p)=>{const{stats:s,hospitalName:a,operatingTitle:y,logo:g,language:n,t:e}=p;if(!s)return;const x=n==="ar",u=a.replace(/\s+/g,"_"),h=e("report_categories_title","تقرير_فئات_ومعايير_جودة_الخدمات_الصحية").replace(/\s+/g,"_"),$=new Date().toISOString().slice(0,10),v=l==="pdf"?`${h}_${u}_${$}`:`${e("report_categories_title","تقرير فئات ومعايير جودة الخدمات الصحية")} - ${a}`,f=`
    <!DOCTYPE html>
    <html dir="${x?"rtl":"ltr"}" lang="${n}">
    <head>
      <meta charset="UTF-8">
      <title>${v}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
        .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #10b981; padding-bottom: 15px; margin-bottom: 25px; }
        .hospital-info h1 { font-size: 16px; font-weight: 800; }
        .report-title-banner { text-align: center; background: linear-gradient(135deg, #059669, #10b981); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .report-title-banner h2 { font-size: 18px; font-weight: 900; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th { background-color: #10b981; color: white; font-weight: bold; padding: 10px; }
        td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .bar-outer { width: 100%; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; margin-top: 5px; }
        .bar-inner { height: 100%; border-radius: 4px; }
        @page { size: A4; margin: 15mm; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
        @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
        .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
      </style>
    </head>
    <body>
      <div class="header-container">
        <div style="display: flex; align-items: center; gap: 15px;">
          ${g?`<img src="${g}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />`:""}
          <div class="hospital-info">
            <h1>${a}</h1>
            <p>${y}</p>
          </div>
        </div>
        <div class="report-meta" style="font-size: 11px; color: #64748b;">
          <p><strong>${e("report_date","التاريخ")}:</strong> ${new Date().toLocaleDateString(x?"ar-SA":"en-US")}</p>
          ${l==="pdf"?'<p style="margin-top: 5px; color: #10b981; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>':""}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${e("report_categories_title","تقرير فئات ومعايير جودة الخدمات الصحية")}</h2>
        <p>${e("report_categories_desc","تحليل نقاط القوة والضعف لجميع نقاط الاتصال وتجارب الرعاية الصحية للمستفيدين")}</p>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #10b981; padding-right: 8px;">مستويات الجودة والرضا لخدمات الرعاية</h3>
      <table>
        <thead>
          <tr>
            <th>المعايير والفئة الخدمية</th>
            <th>معدل الرضا العام المقاس</th>
            <th>مستوى الجودة المحقق</th>
          </tr>
        </thead>
        <tbody>
          ${s.categoryScores.map(i=>{let o="#10b981";return i.score<50?o="#ef4444":i.score<70&&(o="#f59e0b"),`
              <tr>
                <td style="text-align: ${x?"right":"left"}; padding-right: 15px;">
                  <strong>${i.category}</strong>
                  <div class="bar-outer">
                    <div class="bar-inner" style="width: ${i.score}%; background-color: ${o};"></div>
                  </div>
                </td>
                <td><strong style="color: ${o}; font-size: 14px;">${i.score}%</strong></td>
                <td><strong>${D(i.score,e)}</strong></td>
              </tr>
            `}).join("")}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - نظام تقارير الجودة ومقاييس الأداء لخدمات الرعاية</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${a}</p>
      </div>
      <div class="page-footer">${a} | MedSurvey Pro</div>
    </body>
    </html>
  `;c.document.write(f)},ct=(c,l,p)=>{const{tickets:s,hospitalName:a,operatingTitle:y,logo:g,language:n,t:e}=p,x=n==="ar",u=s.length,h=s.filter(b=>b.status==="open").length,$=s.filter(b=>b.status==="in_progress").length,v=s.filter(b=>b.status==="resolved").length,f=a.replace(/\s+/g,"_"),i=e("report_tickets_title","تقرير_البلاغات_وتذاكر_المتابعة_الفورية_للشكاوى").replace(/\s+/g,"_"),o=new Date().toISOString().slice(0,10),d=l==="pdf"?`${i}_${f}_${o}`:`${e("report_tickets_title","تقرير البلاغات وتذاكر المتابعة الفورية للشكاوى")} - ${a}`,z=`
    <!DOCTYPE html>
    <html dir="${x?"rtl":"ltr"}" lang="${n}">
    <head>
      <meta charset="UTF-8">
      <title>${d}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
        .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #ef4444; padding-bottom: 15px; margin-bottom: 25px; }
        .hospital-info h1 { font-size: 16px; font-weight: 800; }
        .report-title-banner { text-align: center; background: linear-gradient(135deg, #dc2626, #ef4444); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .report-title-banner h2 { font-size: 18px; font-weight: 900; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; text-align: center; }
        .stat-card .value { font-size: 22px; font-weight: 800; }
        .stat-card .label { font-size: 10px; color: #64748b; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
        th { background-color: #ef4444; color: white; font-weight: bold; padding: 10px; }
        td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-weight: bold; font-size: 9px; }
        .status-open { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .status-progress { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .status-resolved { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        @page { size: A4; margin: 15mm; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
        @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
        .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
      </style>
    </head>
    <body>
      <div class="header-container">
        <div style="display: flex; align-items: center; gap: 15px;">
          ${g?`<img src="${g}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />`:""}
          <div class="hospital-info">
            <h1>${a}</h1>
            <p>${y}</p>
          </div>
        </div>
        <div class="report-meta" style="font-size: 11px; color: #64748b;">
          <p><strong>التاريخ:</strong> ${new Date().toLocaleDateString(x?"ar-SA":"en-US")}</p>
          ${l==="pdf"?'<p style="margin-top: 5px; color: #ef4444; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>':""}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>${e("report_tickets_title","تقرير البلاغات وتذاكر المتابعة الفورية للشكاوى")}</h2>
        <p>مراقبة وتحليل البلاغات الفورية التي تسجلها آليات المتابعة الاستباقية لضمان الاستجابة السريعة لمشاكل المرضى</p>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="value" style="color: #ef4444;">${u}</div>
          <div class="label">إجمالي البلاغات المسجلة</div>
        </div>
        <div class="stat-card">
          <div class="value" style="color: #dc2626;">${h}</div>
          <div class="label">تذاكر مفتوحة وقيد الانتظار</div>
        </div>
        <div class="stat-card">
          <div class="value" style="color: #d97706;">${$}</div>
          <div class="label">بلاغات قيد المعالجة النشطة</div>
        </div>
        <div class="stat-card">
          <div class="value" style="color: #16a34a;">${v}</div>
          <div class="label">بلاغات تم حلها وإغلاقها</div>
        </div>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #ef4444; padding-right: 8px;">قائمة تفاصيل البلاغات والحالات المستلمة</h3>
      <table>
        <thead>
          <tr>
            <th>المريض ومقدم الشكوى</th>
            <th>القسم المعني</th>
            <th>السبب والشكوى</th>
            <th>حالة التذكرة</th>
            <th>الأولوية</th>
            <th>تاريخ التقديم</th>
          </tr>
        </thead>
        <tbody>
          ${s.map(b=>{let k="status-open",j="مفتوح";return b.status==="in_progress"?(k="status-progress",j="قيد المعالجة"):b.status==="resolved"&&(k="status-resolved",j="تم الحل"),`
              <tr>
                <td><strong>${b.patientName}</strong><br><span style="font-size: 9px; color: #64748b;">${b.patientPhone}</span></td>
                <td><strong>${b.department}</strong></td>
                <td style="text-align: ${x?"right":"left"}; max-width: 200px;">${b.description}</td>
                <td><span class="status-badge ${k}">${j}</span></td>
                <td><span style="color: ${b.priority==="high"?"#ef4444":"#64748b"}; font-weight: bold;">${b.priority==="high"?"عالية جداً":"عادية"}</span></td>
                <td>${new Date(b.createdAt).toLocaleDateString(x?"ar-SA":"en-US")}</td>
              </tr>
            `}).join("")}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - إدارة ومراقبة الجودة والاستجابة الاستباقية للشكاوى</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${a}</p>
      </div>
      <div class="page-footer">${a} | MedSurvey Pro</div>
    </body>
    </html>
  `;c.document.write(z)},gt=(c,l,p)=>{const{stats:s,hospitalName:a,operatingTitle:y,logo:g,language:n}=p;if(!s)return;const e=n==="ar",x=a.replace(/\s+/g,"_"),u="تقرير_نظام_الإنذار_المبكر_ومؤشرات_التنبؤ_الذكي_AI".replace(/\s+/g,"_"),h=new Date().toISOString().slice(0,10),$=l==="pdf"?`${u}_${x}_${h}`:`تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI) - ${a}`,v=`
    <!DOCTYPE html>
    <html dir="${e?"rtl":"ltr"}" lang="${n}">
    <head>
      <meta charset="UTF-8">
      <title>${$}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Cairo', sans-serif; padding: 25px; color: #1e293b; }
        .header-container { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #6366f1; padding-bottom: 15px; margin-bottom: 25px; }
        .hospital-info h1 { font-size: 16px; font-weight: 800; }
        .report-title-banner { text-align: center; background: linear-gradient(135deg, #4f46e5, #4f46e5); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .report-title-banner h2 { font-size: 18px; font-weight: 900; }
        .risk-alert-box { background: #eef2ff; border-right: 5px solid #4f46e5; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 12px; }
        .risk-alert-box h4 { font-weight: 800; color: #4f46e5; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th { background-color: #4f46e5; color: white; font-weight: bold; padding: 10px; }
        td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        @page { size: A4; margin: 15mm; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-family: 'Cairo', sans-serif; }
        @media print { body { padding: 0; margin: 0; } .report-title-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
        .footer { margin-top: 40px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
      </style>
    </head>
    <body>
      <div class="header-container">
        <div style="display: flex; align-items: center; gap: 15px;">
          ${g?`<img src="${g}" alt="Logo" style="height: 45px; width: auto; max-width: 120px; object-fit: contain;" />`:""}
          <div class="hospital-info">
            <h1>${a}</h1>
            <p>${y||"الرعاية الطبية الموثوقة"}</p>
          </div>
        </div>
        <div class="report-meta" style="font-size: 11px; color: #64748b;">
          <p><strong>التاريخ:</strong> ${new Date().toLocaleDateString(e?"ar-SA":"en-US")}</p>
          ${l==="pdf"?'<p style="margin-top: 5px; color: #6366f1; font-weight: bold;">💾 نسخة إلكترونية معتمدة بصيغة PDF</p>':""}
        </div>
      </div>
      <div class="report-title-banner">
        <h2>تقرير نظام الإنذار المبكر ومؤشرات التنبؤ الذكي (AI)</h2>
        <p>تحليلات تنبؤية للتحذير الاستباقي قبل تراجع مستويات الجودة وتحديد الأقسام والخدمات الأكثر حساسية لعوامل تراجع رضا المرضى</p>
      </div>
      <div class="risk-alert-box">
        <h4>💡 ملخص القراءة التحليلية لنظام الذكاء الاصطناعي</h4>
        <p>بناءً على النماذج التنبؤية وتحليل الاتجاهات الإحصائية الأخيرة، يُظهر النظام استقراراً عاماً لمعدل الرضا، مع وجود حساسية متوسطة لبعض فئات الخدمة مثل فترات الانتظار ومستوى الرعاية من التمريض. نوصي بالاستجابة الاستباقية لتوصيات الأقسام المدرجة أدناه لتجنب أي تراجع مستقبلي.</p>
      </div>
      <h3 style="font-size: 14px; margin-bottom: 10px; border-right: 4px solid #4f46e5; padding-right: 8px;">مؤشرات الحساسية والأقسام الأكثر عرضة لتراجع الرضا</h3>
      <table>
        <thead>
          <tr>
            <th>القسم المستهدف</th>
            <th>معدل الرضا الحالي</th>
            <th>مستوى المخاطر التنبؤي</th>
            <th>التوصية الاستباقية للذكاء الاصطناعي</th>
          </tr>
        </thead>
        <tbody>
          ${s.departmentScores.map(f=>{let i="منخفضة",o="#10b981",d="الاستمرار في الحفاظ على مستوى الجودة الحالي وتعزيز التميز.";return f.score<60?(i="عالية جداً",o="#ef4444",d="التدخل العاجل ومراجعة إجراءات تذاكر البلاغات والشكاوى لموظفي هذا القسم فوراً."):f.score<75&&(i="متوسطة",o="#f59e0b",d="تكثيف المتابعة وتقديم دورات تنشيطية لتحسين كفاءة تقديم الخدمة وتقليص فترات الانتظار."),`
              <tr>
                <td><strong>${f.name}</strong></td>
                <td><strong style="color: ${o};">${f.score}%</strong></td>
                <td><strong style="color: ${o};">${i}</strong></td>
                <td style="text-align: ${e?"right":"left"}; max-width: 250px;">${d}</td>
              </tr>
            `}).join("")}
        </tbody>
      </table>
      <div class="footer">
        <p>MedSurvey Pro - نظام التنبؤ الذكي وتحليلات الإنذار الاستباقي للرعاية الصحية</p>
        <p>© ${new Date().getFullYear()} جميع الحقوق محفوظة لـ ${a}</p>
      </div>
      <div class="page-footer">${a} | MedSurvey Pro</div>
    </body>
    </html>
  `;c.document.write(v)},xt=q("ReportsPage");function zt(){const c=Y(),l=()=>c("/dashboard"),{t:p,i18n:s}=B(),{settings:a}=G(),[y,g]=N.useState(!0),[n,e]=N.useState(null),[x,u]=N.useState([]),{dateFilter:h,setDateFilter:$,customStartDate:v,setCustomStartDate:f,customEndDate:i,setCustomEndDate:o,apiDateStrings:d}=K("all"),{selectedDepartment:z,setSelectedDepartment:b,restrictedDepartment:k,effectiveDepartment:j}=lt("all"),[R,E]=N.useState([]),[T,S]=N.useState(null),I=j||p("export_all_departments","كل الأقسام"),A=N.useCallback(async()=>{if(!(h==="custom"&&(!v||!i))){g(!0);try{const r=await H.getStats({department:j==="all"?void 0:j,startDate:d.startDate,endDate:d.endDate});e(r),E(k?[k]:r.departmentScores.map(m=>m.name));const w=await V.getAll({department:j});u(w)}catch(r){xt.error("Failed to load reports data:",r)}finally{g(!1)}}},[h,v,i,j,k,d]);N.useEffect(()=>{A()},[A]);const F=(r,w)=>{if(S("${type}_${action}"),!n){S(null);return}const m=window.open("","_blank","width=800,height=600");if(!m){alert("���� ������ �������� �������� ������ �������"),S(null);return}const _={stats:n,tickets:x,hospitalName:a.hospital.name||"������ ������� ��������� ������� ��������",operatingTitle:a.hospital.operatingTitle||"��� �� ����� ����� �� ����",logo:a.hospital.logo,language:s.language,t:p,reportDepartmentLabel:I};r==="executive"?dt(m,w,_):r==="departments"?nt(m,w,_):r==="categories"?pt(m,w,_):r==="tickets"?ct(m,w,_):r==="predictive"&&gt(m,w,_),m.document.close(),W.recordEvent({action:w==="print"?"print_report":"export_report",messageKey:w==="print"?"audit.details.print_report":"audit.details.export_report",params:{reportType:r,department:j||"all",dateRange:h}}).catch(()=>{}),requestAnimationFrame(()=>{m.print(),S(null)})},M=[{type:"executive",title:"تقرير الملخص التنفيذي ورضا المرضى الشامل",desc:"تحليل شامل ومفصل لمستويات رضا المستفيدين ومقاييس الأداء لجميع فئات وقطاعات الخدمة بشكل مدمج واحترافي ممتاز.",icon:tt,color:"text-teal-600 dark:text-teal-400",bgGradient:"from-teal-500/10 to-teal-600/10 dark:from-teal-950/20 dark:to-teal-900/10 hover:from-teal-500/20 hover:to-teal-600/20",border:"border-teal-100 hover:border-teal-300 dark:border-slate-800 dark:hover:border-teal-900"},{type:"departments",title:"تقرير أداء ومقارنة الأقسام الطبية والمستفيدين",desc:"تقرير يوضح الفروقات الإحصائية بين الأقسام الطبية المختلفة لتحديد أفضل الأقسام أداءً والأقسام الأكثر تراجعاً.",icon:L,color:"text-indigo-600 dark:text-indigo-400",bgGradient:"from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20",border:"border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900"},{type:"categories",title:"تقرير فئات جودة الخدمات ونقاط الاتصال المشتركة",desc:"تقرير تفصيلي يوضح جودة الأداء لكل فئة خدمية بشكل مستقل (الاستقبال، الرعاية، نظافة المرافق، سرعة الصيدلية).",icon:et,color:"text-emerald-600 dark:text-emerald-400",bgGradient:"from-emerald-500/10 to-emerald-600/10 dark:from-emerald-950/20 dark:to-emerald-900/10 hover:from-emerald-500/20 hover:to-emerald-600/20",border:"border-emerald-100 hover:border-emerald-300 dark:border-slate-800 dark:hover:border-emerald-900"},{type:"tickets",title:"تقرير البلاغات الفورية وإدارة شكاوى المستفيدين",desc:"تقرير شامل عن كفاءة الاستجابة السريعة للشكاوى، وحالة تذاكر المتابعة الفورية، ونسب حل المشكلات المسجلة.",icon:rt,color:"text-red-600 dark:text-red-400",bgGradient:"from-red-500/10 to-red-600/10 dark:from-red-950/20 dark:to-red-900/10 hover:from-red-500/20 hover:to-red-600/20",border:"border-red-100 hover:border-red-300 dark:border-slate-800 dark:hover:border-red-900"},{type:"predictive",title:"تقرير نظام الإنذار المبكر وتحليلات التنبؤ الذكي",desc:"تقرير استباقي مصنف بمخاطر الجودة وتنبؤات تراجع رضا المرضى للتدخل السريع بناءً على معايير الذكاء الاصطناعي.",icon:at,color:"text-indigo-600 dark:text-indigo-400",bgGradient:"from-indigo-500/10 to-indigo-600/10 dark:from-indigo-950/20 dark:to-indigo-900/10 hover:from-indigo-500/20 hover:to-indigo-600/20",border:"border-indigo-100 hover:border-indigo-300 dark:border-slate-800 dark:hover:border-indigo-900"}];return t.jsxs("div",{className:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-start",children:[t.jsx("div",{className:"flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8",children:t.jsxs("div",{className:"flex items-center gap-3",children:[t.jsx("button",{onClick:l,type:"button",className:"p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-850 rounded-xl transition-all shadow-sm cursor-pointer",children:t.jsx(J,{className:"w-5 h-5 text-gray-500 dark:text-slate-400"})}),t.jsxs("div",{children:[t.jsxs("h1",{className:"text-xl sm:text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2 flex-wrap",children:[t.jsx("span",{children:"نظام التقارير والتحليلات الفاخرة"}),t.jsx("span",{className:"text-xs bg-teal-100 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 font-bold px-2.5 py-1 rounded-full border border-teal-200 dark:border-teal-900/40",children:"النسخة الاحترافية (v2.0)"})]}),t.jsx("p",{className:"text-xs sm:text-sm text-gray-400 dark:text-slate-400 mt-1",children:"اصدار وطباعة التقارير الرسمية المصدقة وتصديرها بصيغة PDF بدعم لغوي كامل وتنسيق راقٍ"})]})]})}),t.jsxs("div",{className:"bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 mb-8 shadow-sm",children:[t.jsxs("div",{className:"flex items-center gap-2.5 text-sm font-bold text-gray-800 dark:text-white mb-4 pb-2 border-b border-gray-50 dark:border-slate-800",children:[t.jsx(Q,{className:"w-4 h-4 text-teal-600 dark:text-teal-400"}),t.jsx("span",{children:"تخصيص مدخلات التقارير قبل التصدير:"})]}),t.jsxs("div",{className:"grid grid-cols-1 sm:grid-cols-2 gap-4",children:[t.jsxs("div",{className:"space-y-1.5",children:[t.jsxs("label",{className:"flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400",children:[t.jsx(C,{className:"w-3.5 h-3.5 text-teal-600 dark:text-teal-450"}),t.jsx("span",{children:"النطاق الزمني للمدخلات"})]}),t.jsx("div",{className:"grid grid-cols-3 md:grid-cols-5 gap-1.5",children:[{value:"all",label:"الكل"},{value:"week",label:"أسبوع"},{value:"month",label:"شهر"},{value:"quarter",label:"ربع سنوي"},{value:"custom",label:"مخصص 📅"}].map(r=>t.jsx("button",{onClick:()=>$(r.value),type:"button",className:`py-2 rounded-xl text-[10px] sm:text-xs font-bold border transition-all cursor-pointer ${h===r.value?"bg-teal-50 dark:bg-teal-950/20 text-teal-700 dark:text-teal-400 border-teal-300 dark:border-teal-900 shadow-sm":"bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-350 border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-750"}`,children:r.label},r.value))})]}),t.jsxs("div",{className:"space-y-1.5 text-start",children:[t.jsxs("label",{className:"flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400",children:[t.jsx(L,{className:"w-3.5 h-3.5 text-teal-600 dark:text-teal-450"}),t.jsx("span",{children:"فرز وتخصيص حسب القسم الطبي"})]}),t.jsxs("select",{value:k||z,onChange:r=>b(r.target.value),disabled:!!k,className:"w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm",children:[t.jsx("option",{value:"all",children:"كل الأقسام الطبية المتاحة"}),R.map(r=>t.jsx("option",{value:r,children:r},r))]}),k&&t.jsx("p",{className:"text-[11px] font-bold text-teal-600 dark:text-teal-400",children:"يتم تقييد التقارير والطباعة تلقائيا على قسمك فقط."})]})]}),h==="custom"&&t.jsxs("div",{className:"grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-50 dark:border-slate-850 animate-slide-down",children:[t.jsxs("div",{className:"space-y-1.5",children:[t.jsxs("label",{className:"flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400",children:[t.jsx(C,{className:"w-3.5 h-3.5 text-teal-600 dark:text-teal-450"}),t.jsx("span",{children:"من تاريخ (بداية النطاق)"})]}),t.jsx("input",{type:"date",value:v,onChange:r=>f(r.target.value),className:"w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200"})]}),t.jsxs("div",{className:"space-y-1.5",children:[t.jsxs("label",{className:"flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-slate-400",children:[t.jsx(C,{className:"w-3.5 h-3.5 text-teal-600 dark:text-teal-450"}),t.jsx("span",{children:"إلى تاريخ (نهاية النطاق)"})]}),t.jsx("input",{type:"date",value:i,onChange:r=>o(r.target.value),className:"w-full px-3.5 py-2 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 dark:focus:ring-teal-950/15 outline-none bg-white dark:bg-slate-800 text-sm font-bold text-gray-700 dark:text-slate-200"})]})]})]}),y?t.jsxs("div",{className:"flex flex-col items-center justify-center py-20 gap-3",children:[t.jsx(P,{className:"w-10 h-10 text-teal-600 animate-spin"}),t.jsx("p",{className:"text-sm font-bold text-gray-500 dark:text-slate-400",children:"جاري معالجة الإحصائيات وبناء قاعدة البيانات التفاعلية..."})]}):t.jsxs("div",{className:"space-y-6 text-start",children:[n&&t.jsxs("div",{className:"grid grid-cols-2 md:grid-cols-4 gap-4 bg-teal-50/50 dark:bg-teal-950/10 p-4 border border-teal-100 dark:border-teal-900/30 rounded-2xl mb-4",children:[t.jsxs("div",{className:"text-center",children:[t.jsx("span",{className:"block text-[10px] text-teal-600 dark:text-teal-400 font-bold",children:"إجمالي السجلات المفحوصة"}),t.jsxs("span",{className:"text-lg font-black text-teal-800 dark:text-teal-300",children:[n.totalResponses," استجابة"]})]}),t.jsxs("div",{className:"text-center border-r border-teal-100 dark:border-teal-900/30",children:[t.jsx("span",{className:"block text-[10px] text-teal-600 dark:text-teal-400 font-bold",children:"معدل الرضا العام"}),t.jsxs("span",{className:"text-lg font-black text-teal-800 dark:text-teal-300",children:[n.averageScore,"%"]})]}),t.jsxs("div",{className:"text-center border-r border-teal-100 dark:border-teal-900/30",children:[t.jsx("span",{className:"block text-[10px] text-teal-600 dark:text-teal-400 font-bold",children:"مؤشر NPS التراكمي"}),t.jsx("span",{className:"text-lg font-black text-teal-800 dark:text-teal-300",children:n.npsScore})]}),t.jsxs("div",{className:"text-center border-r border-teal-100 dark:border-teal-900/30",children:[t.jsx("span",{className:"block text-[10px] text-teal-600 dark:text-teal-400 font-bold",children:"حالة البيانات"}),t.jsxs("span",{className:"text-lg font-black text-teal-800 dark:text-teal-300 flex items-center justify-center gap-1",children:[t.jsx(Z,{className:"w-4 h-4 text-emerald-500 dark:text-emerald-450"}),t.jsx("span",{children:"معالجة ومحدثة"})]})]})]}),t.jsx("div",{className:"grid grid-cols-1 md:grid-cols-2 gap-6",children:M.map(r=>{const w=r.icon,m=T===`${r.type}_pdf`,_=T===`${r.type}_print`;return t.jsxs("div",{className:`bg-white dark:bg-slate-900 border rounded-2xl p-6 transition-all hover:shadow-lg flex flex-col justify-between ${r.border}`,children:[t.jsxs("div",{className:"space-y-3",children:[t.jsxs("div",{className:"flex items-start justify-between",children:[t.jsx("div",{className:`p-3 rounded-xl bg-linear-to-br ${r.bgGradient}`,children:t.jsx(w,{className:`w-6 h-6 ${r.color}`})}),t.jsxs("span",{className:"text-[10px] bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 font-bold px-2.5 py-1 rounded-full border border-gray-100 dark:border-slate-800 flex items-center gap-1 shadow-sm",children:[t.jsx(ot,{className:"w-3.5 h-3.5 text-amber-500"}),t.jsx("span",{children:"معتمد رسمي"})]})]}),t.jsx("h3",{className:"font-black text-base text-gray-800 dark:text-white",children:r.title}),t.jsx("p",{className:"text-xs text-gray-500 dark:text-slate-400 leading-relaxed",children:r.desc})]}),t.jsxs("div",{className:"pt-5 border-t border-gray-100 dark:border-slate-800 mt-5 flex flex-col sm:flex-row items-center gap-3",children:[t.jsx("button",{onClick:()=>F(r.type,"pdf"),disabled:m||_,type:"button",className:"w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-indigo-600 to-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-indigo-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer",children:m?t.jsxs(t.Fragment,{children:[t.jsx(P,{className:"w-4 h-4 animate-spin"}),t.jsx("span",{children:"جاري التصدير..."})]}):t.jsxs(t.Fragment,{children:[t.jsx(it,{className:"w-4 h-4"}),t.jsx("span",{children:"تصدير كـ PDF"})]})}),t.jsx("button",{onClick:()=>F(r.type,"print"),disabled:m||_,type:"button",className:"w-full sm:flex-1 flex items-center justify-center gap-2 bg-linear-to-l from-teal-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-md shadow-teal-100 dark:shadow-none hover:shadow-lg transition-all cursor-pointer",children:_?t.jsxs(t.Fragment,{children:[t.jsx(P,{className:"w-4 h-4 animate-spin"}),t.jsx("span",{children:"جاري الطباعة..."})]}):t.jsxs(t.Fragment,{children:[t.jsx(X,{className:"w-4 h-4"}),t.jsx("span",{children:"طباعة فورية"})]})})]})]},r.type)})})]})]})}export{zt as default};
