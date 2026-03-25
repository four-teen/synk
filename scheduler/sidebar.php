<?php
$sidebarCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$sidebarActiveKey = 'dashboard';
$sidebarOpenGroupKey = 'preparation_priority';

$sidebarSections = [
    [
        'label' => 'Overview',
        'items' => [
            [
                'key' => 'dashboard',
                'href' => 'index.php',
                'icon_bg' => 'bg-label-primary',
                'icon' => 'bx-home-circle',
                'title' => 'Dashboard',
                'description' => 'College scheduling analytics and status',
                'pages' => ['index.php'],
            ],
        ],
    ],
    [
        'label' => 'Priority Workflow',
        'groups' => [
            [
                'key' => 'preparation_priority',
                'icon_bg' => 'bg-label-primary',
                'icon' => 'bx-list-check',
                'title' => 'Preparation Priority',
                'description' => 'Complete these first before schedule encoding',
                'badge' => 'Priority 1',
                'items' => [
                    [
                        'key' => 'prospectus_builder',
                        'href' => 'manage-prospectus.php',
                        'icon_bg' => 'bg-label-primary',
                        'icon' => 'bx-book-bookmark',
                        'title' => 'Prospectus Viewer',
                        'description' => 'Review subjects by year and semester',
                        'pages' => ['manage-prospectus.php'],
                    ],
                    [
                        'key' => 'faculty',
                        'href' => 'manage-college-faculty.php',
                        'icon_bg' => 'bg-label-info',
                        'icon' => 'bx-user-pin',
                        'title' => 'Manage Faculty',
                        'description' => 'Prepare the faculty roster for workload assignment',
                        'pages' => ['manage-college-faculty.php', 'manage-faculty.php'],
                    ],
                    [
                        'key' => 'rooms',
                        'href' => 'manage-rooms.php',
                        'icon_bg' => 'bg-label-secondary',
                        'icon' => 'bx-door-open',
                        'title' => 'Manage Rooms',
                        'description' => 'Verify room inventory before schedule encoding',
                        'pages' => ['manage-rooms.php'],
                    ],
                    [
                        'key' => 'sections',
                        'href' => 'manage-sections.php',
                        'icon_bg' => 'bg-label-success',
                        'icon' => 'bx-grid-alt',
                        'title' => 'Room Sectioning',
                        'description' => 'Finalize sections for room allocation',
                        'pages' => ['manage-sections.php'],
                    ],
                    [
                        'key' => 'offerings',
                        'href' => 'manage-offerings.php',
                        'icon_bg' => 'bg-label-success',
                        'icon' => 'bx-layer-plus',
                        'title' => 'Generate Offerings',
                        'description' => 'Prepare class offerings for the active term',
                        'pages' => ['manage-offerings.php'],
                    ],
                ],
            ],
            [
                'key' => 'scheduling_run',
                'icon_bg' => 'bg-label-warning',
                'icon' => 'bx-calendar-edit',
                'title' => 'Scheduling Run',
                'description' => 'Encode time and room assignments after setup',
                'badge' => 'Priority 2',
                'items' => [
                    [
                        'key' => 'class_schedule',
                        'href' => 'manage-class-schedule.php',
                        'icon_bg' => 'bg-label-danger',
                        'icon' => 'bx-time-five',
                        'title' => 'Class Scheduling',
                        'description' => 'Assign lecture/lab day, time, and room',
                        'pages' => ['manage-class-schedule.php'],
                    ],
                    [
                        'key' => 'faculty_workload',
                        'href' => 'manage-workload.php',
                        'icon_bg' => 'bg-label-warning',
                        'icon' => 'bx-user-check',
                        'title' => 'Faculty Workload',
                        'description' => 'Assign faculty after lecture/lab schedules are encoded',
                        'pages' => ['manage-workload.php', 'manage-workload_v1.php', 'manage-workload_v2.php', 'manage-workload_bkup.php', 'manage-workload_bkup2.php'],
                    ],
                    [
                        'key' => 'workload_simulations',
                        'href' => 'manage-workload-simulations.php',
                        'icon_bg' => 'bg-label-danger',
                        'icon' => 'bx-git-compare',
                        'title' => 'Workload Simulations',
                        'description' => 'Test non-blocking faculty load scenarios across generated offerings',
                        'pages' => ['manage-workload-simulations.php'],
                    ],
                    [
                        'key' => 'room_utilization',
                        'href' => 'manage-room-utilization.php',
                        'icon_bg' => 'bg-label-info',
                        'icon' => 'bx-building-house',
                        'title' => 'Room Utilization',
                        'description' => 'Review room pressure and schedule conflicts',
                        'pages' => ['manage-room-utilization.php'],
                    ],
                    [
                        'key' => 'class_program',
                        'href' => 'manage-class-program.php',
                        'icon_bg' => 'bg-label-primary',
                        'icon' => 'bx-table',
                        'title' => 'Class Program',
                        'description' => 'Review and print a section weekly class grid',
                        'pages' => ['manage-class-program.php'],
                    ],
                    [
                        'key' => 'iso_class_program',
                        'href' => 'manage-iso-class-program.php',
                        'icon_bg' => 'bg-label-secondary',
                        'icon' => 'bx-file',
                        'title' => 'ISO-Class Program',
                        'description' => 'Review and print the ISO program-based class matrix',
                        'pages' => ['manage-iso-class-program.php'],
                    ],
                ],
            ],
            [
                'key' => 'monitoring_group',
                'icon_bg' => 'bg-label-info',
                'icon' => 'bx-line-chart',
                'title' => 'Monitoring',
                'description' => 'Review results after the schedule has been prepared',
                'badge' => 'Priority 3',
                'items' => [
                    [
                        'key' => 'monitoring_workload',
                        'href' => 'manage-faculty-workload.php',
                        'icon_bg' => 'bg-label-primary',
                        'icon' => 'bx-line-chart',
                        'title' => 'Faculty Workloads',
                        'description' => 'Review completed schedules and teaching loads',
                        'pages' => ['manage-faculty-workload.php'],
                    ],
                    [
                        'key' => 'schedule_activity',
                        'href' => 'manage-schedule-activity.php',
                        'icon_bg' => 'bg-label-info',
                        'icon' => 'bx-radar',
                        'title' => 'Schedule Activity',
                        'description' => 'Track classes by time window, faculty location, and load heat',
                        'pages' => ['manage-schedule-activity.php'],
                    ],
                ],
            ],
        ],
    ],
];

$sidebarMatchFound = false;

foreach ($sidebarSections as $section) {
    foreach (($section['items'] ?? []) as $item) {
        if (in_array($sidebarCurrentPage, $item['pages'], true)) {
            $sidebarActiveKey = $item['key'];
            $sidebarMatchFound = true;
            break;
        }
    }

    if ($sidebarMatchFound) {
        break;
    }

    foreach (($section['groups'] ?? []) as $group) {
        foreach ($group['items'] as $item) {
            if (in_array($sidebarCurrentPage, $item['pages'], true)) {
                $sidebarActiveKey = $item['key'];
                $sidebarOpenGroupKey = $group['key'];
                $sidebarMatchFound = true;
                break 2;
            }
        }
    }

    if ($sidebarMatchFound) {
        break;
    }
}
?>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a href="index.php" class="app-brand-link">
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
              id="path-1"
            ></path>
            <path
              d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z"
              id="path-3"
            ></path>
            <path
              d="M7.50063644,21.2294429 L12.3234468,23.3159332 C14.1688022,24.7579751 14.397098,26.4880487 13.008334,28.506154 C11.6195701,30.5242593 10.3099883,31.790241 9.07958868,32.3040991 C5.78142938,33.4346997 4.13234973,34 4.13234973,34 C4.13234973,34 2.75489982,33.0538207 2.37032616e-14,31.1614621 C-0.55822714,27.8186216 -0.55822714,26.0572515 -4.05231404e-15,25.8773518 C0.83734071,25.6075023 2.77988457,22.8248993 3.3049379,22.52991 C3.65497346,22.3332504 5.05353963,21.8997614 7.50063644,21.2294429 Z"
              id="path-4"
            ></path>
            <path
              d="M20.6,7.13333333 L25.6,13.8 C26.2627417,14.6836556 26.0836556,15.9372583 25.2,16.6 C24.8538077,16.8596443 24.4327404,17 24,17 L14,17 C12.8954305,17 12,16.1045695 12,15 C12,14.5672596 12.1403557,14.1461923 12.4,13.8 L17.4,7.13333333 C18.0627417,6.24967773 19.3163444,6.07059163 20.2,6.73333333 C20.3516113,6.84704183 20.4862915,6.981722 20.6,7.13333333 Z"
              id="path-5"
            ></path>
          </defs>
          <g id="g-app-brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
            <g id="Brand-Logo" transform="translate(-27.000000, -15.000000)">
              <g id="Icon" transform="translate(27.000000, 15.000000)">
                <g id="Mask" transform="translate(0.000000, 8.000000)">
                  <mask id="mask-2" fill="white">
                    <use xlink:href="#path-1"></use>
                  </mask>
                  <use fill="#696cff" xlink:href="#path-1"></use>
                  <g id="Path-3" mask="url(#mask-2)">
                    <use fill="#696cff" xlink:href="#path-3"></use>
                    <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-3"></use>
                  </g>
                  <g id="Path-4" mask="url(#mask-2)">
                    <use fill="#696cff" xlink:href="#path-4"></use>
                    <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-4"></use>
                  </g>
                </g>
                <g
                  id="Triangle"
                  transform="translate(19.000000, 11.000000) rotate(-300.000000) translate(-19.000000, -11.000000) "
                >
                  <use fill="#696cff" xlink:href="#path-5"></use>
                  <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-5"></use>
                </g>
              </g>
            </g>
          </g>
        </svg>
      </span>
      <span class="app-brand-text demo menu-text fw-bolder ms-2">Synk</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
      <i class="bx bx-chevron-left bx-sm align-middle"></i>
    </a>
  </div>

  <style>
    #layout-menu .sidebar-action-card {
      --sidebar-card-radius: 0.75rem;
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.72rem 0.78rem;
      margin: 0.42rem 0.2rem;
      border: 1px solid #e4e8f0;
      border-radius: var(--sidebar-card-radius);
      background: #ffffff;
      transition: all 0.2s ease;
      white-space: normal;
      overflow: visible;
      isolation: isolate;
    }

    #layout-menu .sidebar-action-card::before,
    #layout-menu .sidebar-subcard::before {
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
      offset-path: inset(0.5px round calc(var(--sidebar-card-radius) - 0.5px));
      offset-distance: 0%;
      animation: sidebar-border-orbit 4s linear infinite paused;
      transition: opacity 0.2s ease;
      pointer-events: none;
      z-index: 3;
    }

    #layout-menu .sidebar-action-card:hover::before,
    #layout-menu .sidebar-subcard:hover::before {
      opacity: 1;
      animation-play-state: running;
    }

    #layout-menu .sidebar-action-card > *,
    #layout-menu .sidebar-subcard > * {
      position: relative;
      z-index: 2;
    }

    #layout-menu .sidebar-action-card:hover {
      border-color: #d7b693;
      box-shadow: 0 6px 14px rgba(51, 71, 103, 0.09);
      transform: translateY(-1px);
    }

    #layout-menu .sidebar-action-card.active {
      border-color: #696cff;
      background: #f6f7ff;
    }

    #layout-menu .menu-item.open > .sidebar-group-card,
    #layout-menu .sidebar-group-card.is-open {
      border-color: #d5dbff;
      background: #f8f9ff;
    }

    #layout-menu .sidebar-group-card {
      padding-right: 2.1rem;
    }

    #layout-menu .sidebar-group-card::after {
      right: 0.9rem;
      color: #6b778c;
      z-index: 2;
    }

    #layout-menu .sidebar-action-icon {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 32px;
      font-size: 1rem;
    }

    #layout-menu .sidebar-action-content {
      min-width: 0;
    }

    #layout-menu .sidebar-action-title {
      font-size: 0.86rem;
      font-weight: 600;
      line-height: 1.05rem;
      color: #364152;
    }

    #layout-menu .sidebar-action-sub {
      display: block;
      margin-top: 0.14rem;
      font-size: 0.72rem;
      color: #8391a7;
      line-height: 0.92rem;
    }

    #layout-menu .sidebar-group-badge {
      display: inline-flex;
      margin-top: 0.35rem;
      padding: 0.16rem 0.42rem;
      border-radius: 999px;
      background: #eef2ff;
      color: #5561d7;
      font-size: 0.66rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }

    #layout-menu .sidebar-submenu {
      gap: 0.28rem;
      padding: 0.35rem 0.1rem 0.45rem;
      margin: 0 0.2rem 0.45rem;
    }

    #layout-menu .sidebar-submenu .menu-item {
      width: 100%;
    }

    #layout-menu .sidebar-subcard {
      --sidebar-card-radius: 0.72rem;
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.58rem;
      padding: 0.62rem 0.75rem;
      border-radius: var(--sidebar-card-radius);
      border: 1px solid transparent;
      background: #f8fafc;
      white-space: normal;
      transition: all 0.2s ease;
      overflow: visible;
      isolation: isolate;
    }

    #layout-menu .sidebar-subcard:hover {
      background: #ffffff;
      border-color: #d7b693;
    }

    #layout-menu .sidebar-submenu .menu-item.active > .sidebar-subcard {
      border-color: #696cff;
      background: #f6f7ff;
      box-shadow: 0 6px 14px rgba(105, 108, 255, 0.12);
    }

    #layout-menu .sidebar-subicon {
      width: 28px;
      height: 28px;
      border-radius: 9px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 28px;
      font-size: 0.92rem;
    }

    #layout-menu .sidebar-subcontent {
      min-width: 0;
    }

    #layout-menu .sidebar-subtitle {
      font-size: 0.8rem;
      font-weight: 600;
      line-height: 1rem;
      color: #364152;
    }

    #layout-menu .sidebar-subdesc {
      display: block;
      margin-top: 0.12rem;
      font-size: 0.69rem;
      color: #8391a7;
      line-height: 0.88rem;
    }

    @keyframes sidebar-border-orbit {
      to {
        offset-distance: 100%;
      }
    }
  </style>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <?php foreach ($sidebarSections as $index => $section): ?>
      <li class="menu-header small text-uppercase<?php echo $index === 0 ? ' mt-1' : ' mt-2'; ?>">
        <span class="menu-header-text"><?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?></span>
      </li>

      <?php foreach (($section['items'] ?? []) as $item): ?>
        <li class="menu-item px-2<?php echo $sidebarActiveKey === $item['key'] ? ' active' : ''; ?>">
          <a
            href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
            class="menu-link sidebar-action-card<?php echo $sidebarActiveKey === $item['key'] ? ' active' : ''; ?>"
          >
            <span class="sidebar-action-icon <?php echo htmlspecialchars($item['icon_bg'], ENT_QUOTES, 'UTF-8'); ?>">
              <i class="bx <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
            </span>
            <div class="sidebar-action-content">
              <div class="sidebar-action-title"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></div>
              <small class="sidebar-action-sub"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
          </a>
        </li>
      <?php endforeach; ?>

      <?php foreach (($section['groups'] ?? []) as $group): ?>
        <?php
          $groupHasActiveItem = false;

          foreach ($group['items'] as $groupItem) {
              if ($sidebarActiveKey === $groupItem['key']) {
                  $groupHasActiveItem = true;
                  break;
              }
          }

          $groupIsOpen = $sidebarOpenGroupKey === $group['key'];
        ?>
        <li class="menu-item px-2<?php echo $groupHasActiveItem ? ' active' : ''; ?><?php echo $groupIsOpen ? ' open' : ''; ?>">
          <a
            href="javascript:void(0);"
            class="menu-link menu-toggle sidebar-action-card sidebar-group-card<?php echo $groupHasActiveItem ? ' active' : ''; ?><?php echo $groupIsOpen ? ' is-open' : ''; ?>"
          >
            <span class="sidebar-action-icon <?php echo htmlspecialchars($group['icon_bg'], ENT_QUOTES, 'UTF-8'); ?>">
              <i class="bx <?php echo htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
            </span>
            <div class="sidebar-action-content">
              <div class="sidebar-action-title"><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></div>
              <small class="sidebar-action-sub"><?php echo htmlspecialchars($group['description'], ENT_QUOTES, 'UTF-8'); ?></small>
              <span class="sidebar-group-badge"><?php echo htmlspecialchars($group['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </a>

          <ul class="menu-sub sidebar-submenu">
            <?php foreach ($group['items'] as $item): ?>
              <li class="menu-item<?php echo $sidebarActiveKey === $item['key'] ? ' active' : ''; ?>">
                <a
                  href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                  class="menu-link sidebar-subcard"
                >
                  <span class="sidebar-subicon <?php echo htmlspecialchars($item['icon_bg'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bx <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                  </span>
                  <div class="sidebar-subcontent">
                    <div class="sidebar-subtitle"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <small class="sidebar-subdesc"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></small>
                  </div>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </ul>
</aside>
