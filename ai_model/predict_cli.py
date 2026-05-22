#!/usr/bin/env python3
"""CLI entry for PHP — outputs one JSON line."""

import json
import sys

from inference import predict

if __name__ == '__main__':
    try:
        if len(sys.argv) > 1:
            text = ' '.join(sys.argv[1:])
        else:
            payload = json.load(sys.stdin)
            text = payload.get('message', '')
        print(json.dumps(predict(text), ensure_ascii=False))
    except Exception as exc:
        print(json.dumps({'error': str(exc), 'engine': 'error'}))
        sys.exit(1)
