<?php
$studentSidebarCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$studentSidebarItems = [
    [
        'key' => 'dashboard',
        'href' => 'index.php',
        'icon_bg' => 'bg-label-primary',
        'icon' => 'bx-home-circle',
        'title' => 'Dashboard',
        'description' => 'Current term overview and student quick access',
        'pages' => ['index.php'],
    ],
    [
        'key' => 'prospectus',
        'href' => 'prospectus.php',
        'icon_bg' => 'bg-label-success',
        'icon' => 'bx-book-content',
        'title' => 'Prospectus Viewer',
        'description' => 'Browse curriculum subjects by program and version',
        'pages' => ['prospectus.php'],
    ],
    [
        'key' => 'class_program',
        'href' => 'class-program.php',
        'icon_bg' => 'bg-label-info',
        'icon' => 'bx-user-voice',
        'title' => 'Faculty Evaluation',
        'description' => 'Evaluate faculty members who handled your subjects',
        'pages' => ['class-program.php'],
    ],
];

$studentSidebarActiveKey = 'dashboard';
foreach ($studentSidebarItems as $sidebarItem) {
    if (in_array($studentSidebarCurrentPage, $sidebarItem['pages'], true)) {
        $studentSidebarActiveKey = $sidebarItem['key'];
        break;
    }
}
?>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a href="<?php echo htmlspecialchars(function_exists('synk_student_build_portal_url') ? synk_student_build_portal_url('index.php') : 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="app-brand-link">
      <span class="app-brand-logo demo">
        <svg
          width="25"
          viewBox="0 0 25 42"
          version="1.1"
          xmlns="http://www.w3.org/2000/svg"
          xmlns:xlink="http://www.w3.org/1999/xlink"
        >
          <defs>
            <path
              d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z"
              id="student-path-1"
            ></path>
            <path
              d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z"
              id="student-path-3"
            ></path>
            <path
              d="M7.50063644,21.2294429 L12.3234468,23.3159332 C14.1688022,24.7579751 14.397098,26.4880487 13.008334,28.506154 C11.6195701,30.5242593 10.3099883,31.790241 9.07958868,32.3040991 C5.78142938,33.4346997 4.13234973,34 4.13234973,34 C4.13234973,34 2.75489982,33.0538207 2.37032616e-14,31.1614621 C-0.55822714,27.8186216 -0.55822714,26.0572515 -4.05231404e-15,25.8773518 C0.83734071,25.6075023 2.77988457,22.8248993 3.3049379,22.52991 C3.65497346,22.3332504 5.05353963,21.8997614 7.50063644,21.2294429 Z"
              id="student-path-4"
            ></path>
            <path
              d="M20.6,7.13333333 L25.6,13.8 C26.2627417,14.6836556 26.0836556,15.9372583 25.2,16.6 C24.8538077,16.8596443 24.4327404,17 24,17 L14,17 C12.8954305,17 12,16.1045695 12,15 C12,14.5672596 12.1403557,14.1461923 12.4,13.8 L17.4,7.13333333 C18.0627417,6.24967773 19.3163444,6.07059163 20.2,6.73333333 C20.3516113,6.84704183 20.4862915,6.981722 20.6,7.13333333 Z"
              id="student-path-5"
            ></path>
          </defs>
          <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
            <g transform="translate(-27.000000, -15.000000)">
              <g transform="translate(27.000000, 15.000000)">
                <g transform="translate(0.000000, 8.000000)">
                  <mask id="student-mask-2" fill="white">
                    <use xlink:href="#student-path-1"></use>
                  </mask>
                  <use fill="#696cff" xlink:href="#student-path-1"></use>
                  <g mask="url(#student-mask-2)">
                    <use fill="#696cff" xlink:href="#student-path-3"></use>
                    <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#student-path-3"></use>
                  </g>
                  <g mask="url(#student-mask-2)">
                    <use fill="#696cff" xlink:href="#student-path-4"></use>
                    <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#student-path-4"></use>
                  </g>
                </g>
                <g transform="translate(19.000000, 11.000000) rotate(-300.000000) translate(-19.000000, -11.000000) ">
                  <use fill="#696cff" xlink:href="#student-path-5"></use>
                  <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#student-path-5"></use>
                </g>
              </g>
            </g>
          </g>
        </svg>
      </span>
      <span class="app-brand-text demo menu-text fw-bolder ms-2">Synk Student</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
      <i class="bx bx-chevron-left bx-sm align-middle"></i>
    </a>
  </div>

  <style>
    #layout-menu .student-menu-card {
      --student-card-radius: 0.78rem;
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.78rem 0.82rem;
      margin: 0.42rem 0.2rem;
      border: 1px solid #e4e8f0;
      border-radius: var(--student-card-radius);
      background: #ffffff;
      transition: all 0.2s ease;
      white-space: normal;
      overflow: visible;
      isolation: isolate;
      text-decoration: none !important;
    }

    #layout-menu .student-menu-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 0.34rem;
      height: 0.34rem;
      border-radius: 999px;
      background: radial-gradient(
        circle,
        rgba(255, 221, 186, 0.98) 0%,
        rgba(244, 149, 63, 0.96) 38%,
        rgba(179, 83, 15, 0.88) 62%,
        rgba(179, 83, 15, 0) 100%
      );
      box-shadow:
        0 0 4px rgba(201, 104, 28, 0.8),
        0 0 8px rgba(201, 104, 28, 0.3);
      opacity: 0;
      offset-anchor: center;
      offset-path: inset(0.5px round calc(var(--student-card-radius) - 0.5px));
      offset-distance: 0%;
      animation: student-sidebar-border-orbit 4s linear infinite paused;
      transition: opacity 0.2s ease;
      pointer-events: none;
      z-index: 3;
    }

    #layout-menu .student-menu-card:hover::before {
      opacity: 1;
      animation-play-state: running;
    }

    #layout-menu .student-menu-card > * {
      position: relative;
      z-index: 2;
    }

    #layout-menu .student-menu-card:hover {
      border-color: #d7b693;
      box-shadow: 0 6px 14px rgba(51, 71, 103, 0.09);
      transform: translateY(-1px);
    }

    #layout-menu .student-menu-card.active {
      border-color: #696cff;
      background: #f6f7ff;
      box-shadow: 0 6px 14px rgba(105, 108, 255, 0.12);
    }

    #layout-menu .student-menu-icon {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 34px;
      font-size: 1rem;
    }

    #layout-menu .student-menu-content {
      min-width: 0;
    }

    #layout-menu .student-menu-title {
      font-size: 0.87rem;
      font-weight: 600;
      line-height: 1.08rem;
      color: #364152;
    }

    #layout-menu .student-menu-desc {
      display: block;
      margin-top: 0.14rem;
      font-size: 0.72rem;
      color: #8391a7;
      line-height: 0.92rem;
    }

    @keyframes student-sidebar-border-orbit {
      to {
        offset-distance: 100%;
      }
    }
  </style>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <li class="menu-header small text-uppercase mt-1">
      <span class="menu-header-text">Student Portal</span>
    </li>

    <?php foreach ($studentSidebarItems as $sidebarItem): ?>
      <li class="menu-item px-2<?php echo $studentSidebarActiveKey === $sidebarItem['key'] ? ' active' : ''; ?>">
        <a
          href="<?php echo htmlspecialchars(function_exists('synk_student_build_portal_url') ? synk_student_build_portal_url($sidebarItem['href']) : $sidebarItem['href'], ENT_QUOTES, 'UTF-8'); ?>"
          class="menu-link student-menu-card<?php echo $studentSidebarActiveKey === $sidebarItem['key'] ? ' active' : ''; ?>"
        >
          <span class="student-menu-icon <?php echo htmlspecialchars($sidebarItem['icon_bg'], ENT_QUOTES, 'UTF-8'); ?>">
            <i class="bx <?php echo htmlspecialchars($sidebarItem['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
          </span>
          <div class="student-menu-content">
            <div class="student-menu-title"><?php echo htmlspecialchars($sidebarItem['title'], ENT_QUOTES, 'UTF-8'); ?></div>
            <small class="student-menu-desc"><?php echo htmlspecialchars($sidebarItem['description'], ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
