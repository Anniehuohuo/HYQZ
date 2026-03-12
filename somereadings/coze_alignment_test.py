import json
import re
import time
from dataclasses import dataclass
from typing import Dict, List, Optional

import requests


@dataclass
class CaseItem:
    name: str
    prompt: str


def parse_sse_text(resp: requests.Response) -> str:
    chunks: List[str] = []
    for raw in resp.iter_lines(decode_unicode=True):
        if not raw:
            continue
        line = raw.strip()
        if not line.startswith("data:"):
            continue
        body = line[5:].strip()
        if body == "[DONE]":
            break
        try:
            obj = json.loads(body)
        except Exception:
            continue
        text = ""
        if isinstance(obj, dict):
            text = str(
                obj.get("content")
                or (obj.get("data") or {}).get("content")
                or (obj.get("message") or {}).get("content")
                or obj.get("answer")
                or ""
            )
        if text:
            chunks.append(text)
    return "".join(chunks).strip()


def call_coze_direct(base_url: str, token: str, bot_id: str, user_id: str, prompt: str, conv_id: str = "") -> Dict:
    url = base_url.rstrip("/") + "/v1/chat"
    payload = {
        "bot_id": bot_id,
        "user_id": user_id,
        "query": prompt,
        "stream": False,
    }
    if conv_id:
        payload["conversation_id"] = conv_id
    headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
    r = requests.post(url, headers=headers, json=payload, timeout=60)
    data = {}
    try:
        data = r.json()
    except Exception:
        data = {"code": -1, "msg": r.text[:500], "data": None}
    text = ""
    cid = ""
    if isinstance(data, dict):
        d = data.get("data") or {}
        if isinstance(d, dict):
            text = str(d.get("content") or "")
            cid = str(d.get("conversation_id") or "")
    return {"ok": int(data.get("code", 1)) == 0, "text": text.strip(), "conversation_id": cid, "raw": data}


def call_app_sse(api_url: str, token: str, agent_id: int, prompt: str, session_id: int = 0) -> Dict:
    payload = {
        "agent_id": str(agent_id),
        "message": prompt,
        "stream": 1,
        "session_id": session_id,
        "format": "text",
    }
    headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
    r = requests.post(api_url, headers=headers, json=payload, timeout=120, stream=True)
    text = parse_sse_text(r)
    return {"ok": bool(text), "text": text}


def score_shape(text: str) -> Dict:
    t = text or ""
    lines = [x for x in re.split(r"\r?\n", t) if x.strip()]
    has_score = bool(re.search(r"(评分|score)\s*[:：]?\s*\d+", t, flags=re.I))
    has_advice = bool(re.search(r"(建议|提升|改进)", t))
    has_noise = bool(re.search(r"(msg_type|ori_req|from_module|section_id|connector_uid)", t))
    bullets = len(re.findall(r"(^|\n)\s*(\d+[\.、]|[-*])\s+", t))
    return {
        "chars": len(t),
        "lines": len(lines),
        "bullets": bullets,
        "has_score": has_score,
        "has_advice": has_advice,
        "has_noise": has_noise,
    }


def diff_ratio(a: str, b: str) -> float:
    la, lb = len(a or ""), len(b or "")
    if la == 0 and lb == 0:
        return 0.0
    return abs(la - lb) / max(la, lb, 1)


def run():
    cfg = {
        "coze_base_url": "https://api.coze.cn",
        "coze_pat": "REPLACE_COZE_PAT",
        "coze_bot_id": "REPLACE_BOT_ID",
        "coze_user_id": "qa_user_001",
        "app_chat_url": "https://REPLACE_DOMAIN/api/v1/ai/chat",
        "app_token": "REPLACE_APP_USER_TOKEN",
        "app_agent_id": 1,
    }
    cases = [
        CaseItem("评分场景", "孩子不小心把水洒在地板上，妈妈想训练责任边界，请给出对话和评分。"),
        CaseItem("普通问答", "亲子沟通里，如何避免命令式语气？"),
    ]
    conv_id = ""
    for i, c in enumerate(cases, 1):
        print("=" * 80)
        print(f"[CASE {i}] {c.name}")
        c1 = call_coze_direct(cfg["coze_base_url"], cfg["coze_pat"], cfg["coze_bot_id"], cfg["coze_user_id"], c.prompt, conv_id)
        if c1["conversation_id"]:
            conv_id = c1["conversation_id"]
        c2 = call_app_sse(cfg["app_chat_url"], cfg["app_token"], cfg["app_agent_id"], c.prompt)
        s1 = score_shape(c1["text"])
        s2 = score_shape(c2["text"])
        dr = diff_ratio(c1["text"], c2["text"])
        print("[COZE] ", json.dumps(s1, ensure_ascii=False))
        print("[APP ] ", json.dumps(s2, ensure_ascii=False))
        print(f"[LEN_DIFF_RATIO] {dr:.3f}")
        print("[COZE_TEXT_HEAD]", (c1["text"] or "")[:180].replace("\n", "\\n"))
        print("[APP_TEXT_HEAD ]", (c2["text"] or "")[:180].replace("\n", "\\n"))
        if s2["has_noise"]:
            print("[WARN] APP结果含调试噪音字段")
        time.sleep(0.6)


if __name__ == "__main__":
    run()

