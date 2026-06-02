<!DOCTYPE html>
<html dir="{{ $isAr ? 'rtl' : 'ltr' }}" lang="{{ $isAr ? 'ar' : 'en' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $isAr ? 'تقرير استبيانات رضا المرضى' : 'Survey Responses Report' }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
      padding: 25px;
      color: #1e293b;
      background: #ffffff;
      line-height: 1.6;
      font-size: 12px;
    }
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid #0d9488;
      padding-bottom: 20px;
      margin-bottom: 30px;
    }
    .header-right { display: flex; align-items: center; gap: 15px; }
    .header-info h1 { font-size: 18px; font-weight: 800; color: #0f172a; margin: 0; }
    .header-info p { font-size: 11px; color: #64748b; margin-top: 2px; }
    .header-left { text-align: {{ $isAr ? 'left' : 'right' }}; }
    .header-meta { font-size: 12px; color: #64748b; }
    .header-meta strong { color: #0f172a; }
    .report-banner {
      text-align: center;
      background: linear-gradient(135deg, #0f172a, #1e293b);
      color: white;
      padding: 25px;
      border-radius: 16px;
      margin-bottom: 30px;
    }
    .report-banner h2 { font-size: 20px; font-weight: 900; margin: 0; }
    .report-banner p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
    .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; text-align: center; }
    .stat-card .val { font-size: 26px; font-weight: 800; color: #0d9488; }
    .stat-card .lbl { font-size: 11px; color: #64748b; font-weight: 600; margin-top: 5px; }
    .section { margin-bottom: 30px; }
    .section-title {
      font-size: 15px; font-weight: 800; color: #0f172a;
      border-{{ $isAr ? 'right' : 'left' }}: 4px solid #0d9488;
      padding-{{ $isAr ? 'right' : 'left' }}: 10px;
      margin-bottom: 15px;
    }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
    th {
      background-color: #0d9488; color: white; font-weight: 700;
      padding: 10px; text-align: center;
    }
    td { padding: 10px; border: 1px solid #e2e8f0; text-align: center; }
    tr:nth-child(even) { background-color: #f8fafc; }
    .badge {
      display: inline-block; padding: 3px 8px; border-radius: 12px;
      font-size: 10px; font-weight: 700;
    }
    .badge-excellent { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .badge-good { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .badge-average { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .badge-poor { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
    .footer {
      margin-top: 40px; text-align: center; font-size: 10px;
      color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px;
    }
    @page { size: A4; margin: 15mm; }
    @media print {
      body { padding: 0; margin: 0; }
      .report-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .logo { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .stat-card { break-inside: avoid; }
      .section { break-inside: avoid; }
    }
  </style>
</head>
<body>
  @php
    $totalResponses = $responses->count();
    $avgScore = round($averageScore ?? 0, 1);
    
    // Satisfaction Distribution
    $excellent = $responses->filter(fn($r) => $r->overallScore >= 85)->count();
    $good = $responses->filter(fn($r) => $r->overallScore >= 70 && $r->overallScore < 85)->count();
    $average = $responses->filter(fn($r) => $r->overallScore >= 50 && $r->overallScore < 70)->count();
    $poor = $responses->filter(fn($r) => $r->overallScore < 50)->count();
    
    // Department scores
    $deptScores = $responses->groupBy('department')->map(function($items, $dept) {
      return [
        'name' => $dept ?: ($isAr ? 'غير محدد' : 'Unspecified'),
        'count' => $items->count(),
        'score' => round($items->avg('overallScore'), 1),
      ];
    })->sortByDesc('score');
    
    $getLevel = function($score) use ($isAr) {
      if ($score >= 85) return $isAr ? 'ممتاز' : 'Excellent';
      if ($score >= 70) return $isAr ? 'جيد' : 'Good';
      if ($score >= 50) return $isAr ? 'متوسط' : 'Average';
      return $isAr ? 'ضعيف' : 'Poor';
    };
    
    $getBadgeClass = function($level) {
      $l = strtolower($level);
      if (str_contains($l, 'ممتاز') || str_contains($l, 'excellent')) return 'badge-excellent';
      if (str_contains($l, 'جيد') || str_contains($l, 'good')) return 'badge-good';
      if (str_contains($l, 'متوسط') || str_contains($l, 'average')) return 'badge-average';
      if (str_contains($l, 'ضعيف') || str_contains($l, 'poor')) return 'badge-poor';
      return 'badge-good';
    };
    
    $scoreColor = function($score) {
      if ($score >= 85) return '#10b981';
      if ($score >= 70) return '#3b82f6';
      if ($score >= 50) return '#f59e0b';
      return '#ef4444';
    };
  @endphp

  <div class="header">
    <div class="header-right">
      @if(!empty($hospitalLogo))
        <img src="{{ $hospitalLogo }}" alt="{{ $hospitalName }}" class="logo" style="height:48px; width:auto; border-radius:8px; object-fit:contain;">
      @endif
      <div class="header-info">
        <h1>{{ $hospitalName }}</h1>
        <p>{{ $isAr ? 'نظام استبيانات رضا المرضى' : 'Patient Satisfaction System' }}</p>
      </div>
    </div>
    <div class="header-left">
      <div class="header-meta">
        <p><strong>{{ $isAr ? 'تاريخ التقرير' : 'Report Date' }}:</strong> {{ now()->format('Y-m-d H:i') }}</p>
        <p><strong>{{ $isAr ? 'إجمالي السجلات' : 'Total Records' }}:</strong> {{ $totalResponses }}</p>
      </div>
    </div>
  </div>

  <div class="report-banner">
    <h2>{{ $isAr ? 'تقرير استبيانات رضا المرضى' : 'Survey Responses Report' }}</h2>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="val">{{ $totalResponses }}</div>
      <div class="lbl">{{ $isAr ? 'إجمالي الاستجابات' : 'Total Responses' }}</div>
    </div>
    <div class="stat-card">
      <div class="val">{{ $avgScore }}%</div>
      <div class="lbl">{{ $isAr ? 'معدل الرضا العام' : 'Overall Satisfaction' }}</div>
    </div>
    <div class="stat-card">
      <div class="val">{{ $npsScore ?? '—' }}</div>
      <div class="lbl">{{ $isAr ? 'مؤشر NPS (ولاء المراجعين)' : 'NPS Score' }}</div>
    </div>
    <div class="stat-card">
      <div class="val">{{ isset($responseRate) ? $responseRate . '%' : '—' }}</div>
      <div class="lbl">{{ $isAr ? 'معدل نمو النشاط (مقارنة بالفترة السابقة)' : 'Activity Growth Rate (vs. previous period)' }}</div>
    </div>
  </div>

  <div class="section">
    <h3 class="section-title">{{ $isAr ? 'توزيع مستوى رضا المرضى' : 'Satisfaction Distribution' }}</h3>
    <table>
      <thead>
        <tr>
          <th>{{ $isAr ? 'المستوى' : 'Level' }}</th>
          <th>{{ $isAr ? 'العدد' : 'Count' }}</th>
          <th>{{ $isAr ? 'النسبة' : 'Percentage' }}</th>
        </tr>
      </thead>
      <tbody>
        @php
          $distribution = [
            ['level' => $isAr ? 'ممتاز' : 'Excellent', 'count' => $excellent, 'badge' => 'badge-excellent'],
            ['level' => $isAr ? 'جيد' : 'Good', 'count' => $good, 'badge' => 'badge-good'],
            ['level' => $isAr ? 'متوسط' : 'Average', 'count' => $average, 'badge' => 'badge-average'],
            ['level' => $isAr ? 'ضعيف' : 'Poor', 'count' => $poor, 'badge' => 'badge-poor'],
          ];
        @endphp
        @foreach($distribution as $item)
          <tr>
            <td><span class="badge {{ $item['badge'] }}">{{ $item['level'] }}</span></td>
            <td>{{ $item['count'] }}</td>
            <td><strong>{{ $totalResponses > 0 ? round(($item['count'] / $totalResponses) * 100) : 0 }}%</strong></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="section">
    <h3 class="section-title">{{ $isAr ? 'التقييم المقارن للأقسام' : 'Department Comparison' }}</h3>
    <table>
      <thead>
        <tr>
          <th>{{ $isAr ? 'القسم الطبي' : 'Department' }}</th>
          <th>{{ $isAr ? 'عدد الاستجابات' : 'Responses' }}</th>
          <th>{{ $isAr ? 'معدل الرضا العام' : 'Satisfaction Rate' }}</th>
          <th>{{ $isAr ? 'مستوى الأداء' : 'Performance' }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($deptScores as $dept)
          <tr>
            <td><strong>{{ $dept['name'] }}</strong></td>
            <td>{{ $dept['count'] }}</td>
            <td><span style="color: {{ $scoreColor($dept['score']) }}; font-weight: bold;">{{ $dept['score'] }}%</span></td>
            <td><strong>{{ $getLevel($dept['score']) }}</strong></td>
          </tr>
        @empty
          <tr>
            <td colspan="4">{{ $isAr ? 'لا توجد بيانات' : 'No data available' }}</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="section">
    <h3 class="section-title">{{ $isAr ? 'تفاصيل الاستجابات' : 'Responses Details' }} ({{ $totalResponses }})</h3>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>{{ $isAr ? 'الاسم' : 'Name' }}</th>
          <th>{{ $isAr ? 'الهاتف' : 'Phone' }}</th>
          <th>{{ $isAr ? 'القسم' : 'Department' }}</th>
          <th>{{ $isAr ? 'الجنس' : 'Gender' }}</th>
          <th>{{ $isAr ? 'نوع الزيارة' : 'Visit Type' }}</th>
          <th>{{ $isAr ? 'التقييم' : 'Evaluation' }}</th>
          <th>{{ $isAr ? 'التاريخ' : 'Date' }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($responses as $i => $r)
          @php
            $scoreS = 'color: #10b981; font-weight: bold;';
            if($r->overallScore < 50) $scoreS = 'color: #ef4444; font-weight: bold;';
            elseif($r->overallScore < 70) $scoreS = 'color: #f59e0b; font-weight: bold;';
            
            $genderLabel = match(strtolower($r->gender ?? '')) {
              'male' => $isAr ? 'ذكر' : 'Male',
              'female' => $isAr ? 'أنثى' : 'Female',
              default => $r->gender ?: '—',
            };
            
            $visitLabel = match(strtolower($r->visitType ?? '')) {
              'inpatient' => $isAr ? 'تنويم' : 'Inpatient',
              'outpatient' => $isAr ? 'عيادات خارجية' : 'Outpatient',
              'emergency' => $isAr ? 'طوارئ' : 'Emergency',
              default => $r->visitType ?: '—',
            };
          @endphp
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $r->patientName ?: '—' }}</td>
            <td dir="ltr">{{ $r->patientPhone ?: '—' }}</td>
            <td>{{ $r->department ?: '—' }}</td>
            <td>{{ $genderLabel }}</td>
            <td>{{ $visitLabel }}</td>
            <td><span style="{{ $scoreS }}">{{ $r->overallScore }}%</span></td>
            <td>{{ $r->submittedAt ? $r->submittedAt->format('Y-m-d') : '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="8">{{ $isAr ? 'لا توجد استجابات' : 'No responses' }}</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="footer">
    <p>{{ $hospitalName }} — {{ $isAr ? 'نظام استبيانات رضا المرضى' : 'Patient Satisfaction System' }}</p>
    <p>© {{ date('Y') }} | {{ $isAr ? 'جميع الحقوق محفوظة' : 'All Rights Reserved' }}</p>
  </div>

  <script>
    window.onload = function() { window.print(); };
  </script>
</body>
</html>
