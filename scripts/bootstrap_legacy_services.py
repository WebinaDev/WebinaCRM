#!/usr/bin/env python3
"""Rebuild service files with legacy() stubs from existing register_map blocks."""

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def parse_action_map(service_src: str) -> list[tuple[str, str]]:
    block = re.search(
        r"register_map\s*\(\s*array\s*\((.*?)\)\s*\)", service_src, re.DOTALL
    )
    if not block:
        return []
    pairs = []
    for line in block.group(1).splitlines():
        m = re.search(
            r"'([^']+)'\s*=>\s*array\s*\(\s*__CLASS__\s*,\s*'([^']+)'\s*\)",
            line,
        )
        if m:
            pairs.append((m.group(1), m.group(2)))
    return pairs


def rebuild_legacy(path: Path, class_name: str) -> None:
    src = path.read_text(encoding="utf-8")
    pairs = parse_action_map(src)
    if not pairs:
        print(f"skip {path.name}: no map")
        return
    title = class_name.replace("WebinoCRM_", "").replace("_", " ")
    lines = [
        "<?php",
        "/**",
        f" * {title} service layer.",
        " *",
        " * @package WebinoCRM",
        " */",
        "",
        "if ( ! defined( 'ABSPATH' ) ) {",
        "\texit;",
        "}",
        "",
        f"class {class_name} {{",
        "\tuse WebinoCRM_Domain_Service_Trait;",
        "",
    ]
    reg = []
    for action, method in pairs:
        lines.append(
            f"\tpublic static function {method}( array $params ) {{\n"
            f"\t\treturn self::legacy( '{action}', $params );\n"
            f"\t}}\n"
        )
        reg.append(f"\t\t\t\t'{action}' => array( __CLASS__, '{method}' ),")
    lines.append("\n\tpublic static function register_actions() {\n\t\tself::register_map(\n\t\t\tarray(\n")
    lines.append("\n".join(reg))
    lines.append("\n\t\t\t)\n\t\t);\n\t}\n}\n")
    path.write_text("\n".join(lines), encoding="utf-8")
    print(f"ok {path.name} ({len(pairs)} methods)")


FILES = [
    ("includes/services/class-leads-service.php", "WebinoCRM_Leads_Service"),
    ("includes/services/class-tasks-service.php", "WebinoCRM_Tasks_Service"),
    ("includes/services/class-tickets-service.php", "WebinoCRM_Tickets_Service"),
    ("includes/services/class-customers-service.php", "WebinoCRM_Customers_Service"),
    ("includes/services/class-appointments-service.php", "WebinoCRM_Appointments_Service"),
    ("includes/services/class-consultations-service.php", "WebinoCRM_Consultations_Service"),
    ("includes/services/class-campaigns-service.php", "WebinoCRM_Campaigns_Service"),
    ("includes/services/class-crm-services-module-service.php", "WebinoCRM_Crm_Services_Module_Service"),
    ("includes/services/class-marketplace-service.php", "WebinoCRM_Marketplace_Service"),
    ("includes/services/class-licenses-service.php", "WebinoCRM_Licenses_Service"),
    ("includes/services/class-modirpayamak-service.php", "WebinoCRM_Modirpayamak_Service"),
    ("includes/services/class-auth-service.php", "WebinoCRM_Auth_Service"),
    ("includes/services/class-settings-crm-service.php", "WebinoCRM_Settings_Crm_Service"),
    ("includes/services/class-profile-service.php", "WebinoCRM_Profile_Service"),
    ("includes/services/class-dashboard-service.php", "WebinoCRM_Dashboard_Service"),
    ("includes/services/class-logs-service.php", "WebinoCRM_Logs_Service"),
    ("includes/services/class-reports-service.php", "WebinoCRM_Reports_Service"),
    ("includes/services/class-visitor-service.php", "WebinoCRM_Visitor_Service"),
    ("includes/services/class-invoices-service.php", "WebinoCRM_Invoices_Service"),
]

if __name__ == "__main__":
    for rel, cls in FILES:
        rebuild_legacy(ROOT / rel, cls)
