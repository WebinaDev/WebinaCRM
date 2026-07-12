#!/usr/bin/env python3
"""Fetch shadcn new-york registry items and write into client/src with @/ imports."""
from __future__ import annotations

import json
import os
import sys
import urllib.request
from pathlib import Path

BASE = "https://ui.shadcn.com/r/styles/new-york"
CACHE = Path(os.environ.get("SHADCN_REGISTRY_CACHE", "/tmp/shadcn-ny"))
ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "src"


def rewrite_imports(content: str) -> str:
    content = content.replace("@/registry/new-york/ui/", "@/components/ui/")
    content = content.replace("@/registry/new-york/lib/", "@/lib/")
    content = content.replace("@/registry/new-york/hooks/", "@/hooks/")
    content = content.replace("@/registry/new-york/components/", "@/components/")
    content = content.replace(
        "@/registry/new-york/blocks/sidebar-08/components/", "@/components/"
    )
    content = content.replace('"use client"\n\n', "")
    content = content.replace('"use client"\r\n\r\n', "")
    return content


def fetch_json(name: str) -> dict:
    cached = CACHE / f"{name}.json"
    if cached.is_file():
        return json.loads(cached.read_text(encoding="utf-8"))
    url = f"{BASE}/{name}.json"
    with urllib.request.urlopen(url, timeout=120) as r:
        return json.loads(r.read().decode())


def registry_ui_out_path(rel: str) -> Path:
    """Map registry paths like ui/button.tsx to src/components/ui/button.tsx."""
    if rel.startswith("ui/"):
        return SRC / "components" / rel
    return SRC / rel


def write_files_from_item(data: dict) -> None:
    for f in data.get("files") or []:
        rel = f["path"]
        content = rewrite_imports(f["content"])
        out = registry_ui_out_path(rel)
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(content, encoding="utf-8")
        print("wrote", out.relative_to(ROOT))


def collect_component_deps(seed_names: list[str]) -> list[str]:
    seen: set[str] = set()
    order: list[str] = []
    stack = list(seed_names)
    while stack:
        name = stack.pop()
        if name in seen:
            continue
        seen.add(name)
        order.append(name)
        try:
            data = fetch_json(name)
        except Exception as e:
            print("skip", name, e, file=sys.stderr)
            continue
        for dep in data.get("registryDependencies") or []:
            if dep not in seen:
                stack.append(dep)
    return order


def main() -> None:
    sidebar08 = fetch_json("sidebar-08")
    sidebar_deps = list(sidebar08.get("registryDependencies") or [])
    names = collect_component_deps(sidebar_deps)
    for n in names:
        write_files_from_item(fetch_json(n))
    for f in sidebar08.get("files") or []:
        if "page.tsx" in f["path"]:
            continue
        rel = f["path"].replace("blocks/sidebar-08/components/", "components/")
        out = SRC / rel
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(rewrite_imports(f["content"]), encoding="utf-8")
        print("wrote", out.relative_to(ROOT))

    login = fetch_json("login-04")
    for dep in login.get("registryDependencies") or []:
        if dep not in names:
            write_files_from_item(fetch_json(dep))
    for f in login.get("files") or []:
        if "page.tsx" in f["path"]:
            continue
        rel = f["path"].replace("blocks/login-04/", "components/blocks/login-04/")
        out = SRC / rel
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(rewrite_imports(f["content"]), encoding="utf-8")
        print("wrote", out.relative_to(ROOT))


if __name__ == "__main__":
    main()
