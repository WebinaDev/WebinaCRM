#!/usr/bin/env python3
"""Patch legacy service stubs from AJAX handlers; selective thin wrappers."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8")


def extract_php_methods(source: str, include_private: bool = True) -> dict[str, str]:
    methods: dict[str, str] = {}
    pattern = re.compile(
        r"(public|private|protected)\s+function\s+(?P<name>\w+)\s*\([^)]*\)\s*\{",
        re.MULTILINE,
    )
    for m in pattern.finditer(source):
        vis, name = m.group(1), m.group("name")
        if name == "__construct":
            continue
        if vis != "public" and not include_private:
            continue
        start = m.end()
        depth = 1
        i = start
        while i < len(source) and depth > 0:
            if source[i] == "{":
                depth += 1
            elif source[i] == "}":
                depth -= 1
            i += 1
        methods[name] = source[start : i - 1].strip()
    return methods


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


def parse_handler_actions(handler_src: str) -> dict[str, str]:
    mapping: dict[str, str] = {}
    for m in re.finditer(
        r"add_action\s*\(\s*['\"]wp_ajax(?:_nopriv)?_([^'\"]+)['\"]\s*,\s*(?:\[\s*\$this\s*,\s*['\"]([^'\"]+)['\"]\s*\]|array\s*\(\s*\$this\s*,\s*['\"]([^'\"]+)['\"]\s*\))",
        handler_src,
    ):
        handler_method = m.group(2) or m.group(3)
        if handler_method:
            mapping[m.group(1)] = handler_method
    return mapping


def strip_check_ajax_block(body: str) -> str:
    """Remove full check_ajax_referer guard blocks."""
    body = re.sub(
        r"if\s*\(\s*!\s*check_ajax_referer\s*\([^)]+\)\s*\)\s*\{[^{}]*wp_send_json_error\s*\([^;]+;\s*\}",
        "",
        body,
        flags=re.DOTALL,
    )
    body = re.sub(
        r"check_ajax_referer\s*\([^)]+\)\s*;",
        "",
        body,
    )
    return body


def collect_this_helpers(body: str) -> set[str]:
    return set(re.findall(r"\$this->(\w+)\s*\(", body))


def convert_private_helpers(handler_src: str, needed: set[str]) -> str:
    if not needed:
        return ""
    sig_pat = re.compile(
        r"(private|protected)\s+function\s+(?P<name>\w+)\s*\((?P<params>[^)]*)\)",
        re.MULTILINE,
    )
    sigs = {m.group("name"): m.group("params") for m in sig_pat.finditer(handler_src)}
    all_methods = extract_php_methods(handler_src, include_private=True)
    chunks = []
    for name in sorted(needed):
        if name not in all_methods:
            continue
        body = transform_body(all_methods[name], handler_src)
        if "wp_send_json" in body:
            body = re.sub(r"wp_send_json_error[^;]+;", "return WebinoCRM_Service_Base::error( __( 'دسترسی غیرمجاز.', 'webinocrm' ) );", body)
        params = sigs.get(name, "")
        chunks.append(
            f"\tprivate static function {name}({params}) {{\n{body}\n\t}}\n"
        )
    return "\n".join(chunks)


def transform_body(body: str, handler_src: str = "") -> str:
    body = strip_check_ajax_block(body)
    body = body.replace("$this->", "self::")

    body = re.sub(
        r"wp_send_json_success\s*\(\s*(\{[\s\S]*?\}|\[[\s\S]*?\]|[^;]+?)\s*\)\s*;",
        r"return WebinoCRM_Service_Base::success( \1 );",
        body,
    )
    body = re.sub(
        r"wp_send_json_error\s*\(\s*(\{[\s\S]*?\}|\[[\s\S]*?\])\s*(?:,\s*(\d+))?\s*\)\s*;",
        lambda m: f"return WebinoCRM_Service_Base::error( {m.group(1)}{', ' + m.group(2) if m.group(2) else ''} );",
        body,
    )

    out: list[str] = []
    for line in body.split("\n"):
        if "$this->check_access()" in line or "self::check_access()" in line:
            continue

        if re.search(r"wp_send_json_error\s*\(", line):
            err = re.search(
                r"wp_send_json_error\s*\(\s*array\s*\(\s*'message'\s*=>\s*(__\([^;]+?\))\s*\)\s*,\s*(\d+)\s*\)",
                line,
            )
            if err:
                out.append(
                    f"\t\treturn WebinoCRM_Service_Base::error( {err.group(1)}, {err.group(2)} );"
                )
                continue
            err = re.search(
                r"wp_send_json_error\s*\(\s*array\s*\(\s*'message'\s*=>\s*(__\([^;]+?\))\s*\)\s*\)",
                line,
            )
            if err:
                out.append(
                    f"\t\treturn WebinoCRM_Service_Base::error( {err.group(1)} );"
                )
                continue
            err = re.search(
                r"wp_send_json_error\s*\(\s*\[\s*'message'\s*=>\s*([^,\]]+)\s*\]",
                line,
            )
            if err:
                out.append(
                    f"\t\treturn WebinoCRM_Service_Base::error( {err.group(1)} );"
                )
                continue

        if "wp_send_json_success" in line or "wp_send_json_error" in line:
            continue
        if line.strip() in ("return;", ""):
            continue
        out.append(line)
    return "\n".join(out)


def build_method(
    name: str, body: str, handler_src: str = "", access: str | None = None
) -> str:
    transformed = transform_body(body, handler_src)
    lines = [
        f"\tpublic static function {name}( array $params ) {{",
        "\t\tWebinoCRM_Service_Base::ensure_dependencies();",
    ]
    if access == "accounting":
        lines += [
            "\t\t$access = self::verify_accounting_access( $params );",
            "\t\tif ( is_array( $access ) ) {",
            "\t\t\treturn $access;",
            "\t\t}",
        ]
    lines.append(transformed)
    lines.append("\t}")
    return "\n".join(lines)


def resolve_handler_method(
    action: str,
    service_method: str,
    action_to_handler: dict[str, str],
    handler_methods: dict[str, str],
) -> str | None:
    if action in action_to_handler:
        return action_to_handler[action]
    if service_method in handler_methods:
        return service_method
    for candidate in (
        f"ajax_{service_method}",
        f"get_{service_method}",
        service_method.replace("_save", "_manage"),
    ):
        if candidate in handler_methods:
            return candidate
    return None


def patch_service_methods(
    service_path: Path,
    handler_sources: list[tuple[Path, str | None]],
    access: str | None = None,
) -> tuple[int, set[str], str]:
    """Patch only legacy() stubs. Returns count, helpers needed aggregate, service class name."""
    service_src = read(service_path)
    class_m = re.search(r"class\s+(\w+)", service_src)
    class_name = class_m.group(1) if class_m else ""
    action_map = parse_action_map(service_src)
    all_helpers: set[str] = set()
    patches: dict[str, str] = {}

    for handler_path, _ in handler_sources:
        handler_src = read(handler_path)
        handler_methods = extract_php_methods(handler_src, include_private=False)
        action_to_handler = parse_handler_actions(handler_src)

        for action, service_method in action_map:
            if service_method in patches:
                continue
            handler_method = resolve_handler_method(
                action, service_method, action_to_handler, handler_methods
            )
            if not handler_method:
                continue
            body = handler_methods.get(handler_method)
            if not body:
                continue
            all_helpers |= collect_this_helpers(body)
            patches[service_method] = build_method(
                service_method, body, handler_src, access
            )

    if not patches:
        return 0, all_helpers, class_name

    helper_block = ""
    for handler_path, _ in handler_sources:
        helper_block += convert_private_helpers(read(handler_path), all_helpers)

    for service_method, new_method in patches.items():
        stub = re.compile(
            r"public static function "
            + re.escape(service_method)
            + r"\s*\(\s*array\s+\$params\s*\)\s*\{\s*return self::legacy\([^;]+;\s*\}",
            re.DOTALL,
        )
        if stub.search(service_src):
            service_src = stub.sub(lambda _m, nm=new_method: nm, service_src, count=1)
        elif not re.search(
            rf"public static function {re.escape(service_method)}\s*\(",
            service_src,
        ):
            service_src = service_src.replace(
                "\tpublic static function register_actions()",
                new_method + "\n\n\tpublic static function register_actions()",
            )

    if helper_block:
        for name in all_helpers:
            if re.search(
                rf"(private|protected)\s+static\s+function\s+{re.escape(name)}\s*\(",
                service_src,
            ):
                helper_block = re.sub(
                    rf"\tprivate static function {re.escape(name)}\([^)]*\)\s*\{{[^}}]*\}}\s*\n",
                    "",
                    helper_block,
                    flags=re.DOTALL,
                )
        if helper_block.strip() and "private static function register_actions" not in helper_block:
            if not re.search(
                rf"private static function {re.escape(sorted(all_helpers)[0])}\s*\(",
                service_src,
            ):
                service_src = service_src.replace(
                    "\tpublic static function register_actions()",
                    helper_block + "\n\tpublic static function register_actions()",
                )

    write(service_path, service_src)
    return len(patches), all_helpers, class_name


def thin_handler_selective(
    handler_path: Path,
    class_name: str,
    delegates: dict[str, tuple[str, str]],
) -> None:
    """Replace only listed handler methods with emit_service; keep others intact."""
    src = read(handler_path)
    if "WebinoCRM_Ajax_Service_Delegate" not in src:
        src = re.sub(
            rf"(class\s+{re.escape(class_name)}\s*\{{)",
            r"\1\n\n\tuse WebinoCRM_Ajax_Service_Delegate;\n",
            src,
            count=1,
        )

    for handler_method, (service_class, service_method) in delegates.items():
        m = re.search(
            rf"public\s+function\s+{re.escape(handler_method)}\s*\([^)]*\)\s*\{{",
            src,
        )
        if not m:
            continue
        start = m.start()
        brace_start = m.end() - 1
        depth = 0
        i = brace_start
        while i < len(src):
            if src[i] == "{":
                depth += 1
            elif src[i] == "}":
                depth -= 1
                if depth == 0:
                    end = i + 1
                    break
            i += 1
        else:
            continue
        replacement = (
            f"public function {handler_method}() {{\n"
            f"\t\t$this->emit_service( array( '{service_class}', '{service_method}' ) );\n"
            f"\t}}"
        )
        src = src[:start] + replacement + src[end:]

    write(handler_path, src)


def run_migration(
    service_rel: str,
    handler_sources: list[tuple[str, str | None]],
    handler_class: str,
    thin: bool = False,
    access: str | None = None,
) -> None:
    service_path = ROOT / service_rel
    sources = [(ROOT / h, None) for h, _ in handler_sources]
    n, _, svc_class = patch_service_methods(service_path, sources, access=access)
    print(f"  patched {n} methods in {service_path.name}")

    if thin and n > 0:
        delegates: dict[str, tuple[str, str]] = {}
        service_src = read(service_path)
        for handler_rel, _ in handler_sources:
            handler_path = ROOT / handler_rel
            action_map = parse_action_map(service_src)
            action_to_handler = parse_handler_actions(read(handler_path))
            for action, service_method in action_map:
                hm = resolve_handler_method(
                    action,
                    service_method,
                    action_to_handler,
                    extract_php_methods(read(handler_path)),
                )
                if hm:
                    delegates[hm] = (svc_class, service_method)
        thin_handler_selective(ROOT / handler_sources[0][0], handler_class, delegates)
        print(f"  thinned {len(delegates)} methods in {handler_sources[0][0]}")


WAVE_A = [
    ("includes/services/class-consultations-service.php", "includes/ajax/class-consultation-ajax-handler.php", "WebinoCRM_Consultation_Ajax_Handler"),
    ("includes/services/class-licenses-service.php", "includes/ajax/class-license-ajax-handler.php", "WebinoCRM_License_Ajax_Handler"),
    ("includes/services/class-marketplace-service.php", "includes/ajax/class-marketplace-ajax-handler.php", "WebinoCRM_Marketplace_Ajax_Handler"),
    ("includes/services/class-crm-services-module-service.php", "includes/ajax/class-services-ajax-handler.php", "WebinoCRM_Services_Ajax_Handler"),
    ("includes/services/class-tickets-service.php", "includes/ajax/class-ticket-ajax-handler.php", "WebinoCRM_Ticket_Ajax_Handler"),
    ("includes/services/class-appointments-service.php", "includes/ajax/class-appointment-ajax-handler.php", "WebinoCRM_Appointment_Ajax_Handler"),
]

WAVE_B = [
    ("includes/services/class-tasks-service.php", "includes/ajax/class-task-ajax-handler.php", "WebinoCRM_Task_Ajax_Handler"),
]

WAVE_TASKS2 = [
    ("includes/services/class-tasks-service.php", "includes/ajax/class-task-ajax-handler.php", "WebinoCRM_Task_Ajax_Handler"),
]

WAVE_C = [
    ("includes/services/class-leads-service.php", "includes/ajax/class-lead-ajax-handler.php", "WebinoCRM_Lead_Ajax_Handler"),
]

WAVE_D = [
    ("includes/services/class-settings-crm-service.php", "includes/ajax/class-settings-ajax-handler.php", "WebinoCRM_Settings_Ajax_Handler"),
    ("includes/services/class-auth-service.php", "includes/ajax/class-login-ajax-handler.php", "WebinoCRM_Login_Ajax_Handler"),
]

WAVE_E = [
    ("includes/services/class-modirpayamak-service.php", "includes/ajax/class-modirpayamak-ajax-handler.php", "WebinoCRM_ModirPayamak_Ajax_Handler"),
]


def main() -> None:
    thin = "--thin" in sys.argv
    wave = None
    for arg in sys.argv[1:]:
        if arg.startswith("--wave="):
            wave = arg.split("=", 1)[1]

    waves = {
        "a": WAVE_A,
        "b": WAVE_B,
        "tasks2": WAVE_TASKS2,
        "c": WAVE_C,
        "d": WAVE_D,
        "e": WAVE_E,
        "f": [],  # custom below
    }

    if wave == "f":
        print("=== Wave F: Customers + Profile + Invoices ===")
        run_migration(
            "includes/services/class-customers-service.php",
            [
                ("includes/ajax/class-user-ajax-handler.php", None),
                ("includes/ajax/class-sms-ajax-handler.php", None),
            ],
            "WebinoCRM_User_Ajax_Handler",
            thin=thin,
        )
        run_migration(
            "includes/services/class-profile-service.php",
            [("includes/ajax/class-user-ajax-handler.php", None)],
            "WebinoCRM_User_Ajax_Handler",
            thin=thin,
        )
        run_migration(
            "includes/services/class-invoices-service.php",
            [
                ("includes/ajax/class-form-ajax-handler.php", None),
                ("includes/ajax/class-project-ajax-handler.php", None),
            ],
            "WebinoCRM_Form_Ajax_Handler",
            thin=thin,
        )
        if thin:
            delegates = {
                "ajax_get_projects_for_customer": (
                    "WebinoCRM_Invoices_Service",
                    "customer_projects",
                ),
            }
            thin_handler_selective(
                ROOT / "includes/ajax/class-project-ajax-handler.php",
                "WebinoCRM_Project_Ajax_Handler",
                delegates,
            )
        return

    if wave and wave in waves:
        print(f"=== Wave {wave.upper()} ===")
        for svc, handler, handler_cls in waves[wave]:
            run_migration(svc, [(handler, None)], handler_cls, thin=thin)
        return

    print("Usage: migrate_ajax_to_service.py --wave=a|b|tasks2|c|d|e|f [--thin]")


if __name__ == "__main__":
    main()
