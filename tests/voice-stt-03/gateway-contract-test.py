import base64
import hashlib
import hmac
import json
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from vosk_gateway import DIAGNOSTIC_ORIGIN, TicketReplayGuard, VoskGateway, diagnostic_session_matches


def b64url(value: bytes) -> str:
    return base64.urlsafe_b64encode(value).decode("ascii").rstrip("=")


def ticket(secret: bytes, claims: dict) -> str:
    payload = b64url(json.dumps(claims, separators=(",", ":")).encode())
    signature = hmac.new(secret, payload.encode("ascii"), hashlib.sha256).digest()
    return payload + "." + b64url(signature)


class GatewayTicketTests(unittest.TestCase):
    def setUp(self):
        self.secret = b"synthetic-only-test-key-32-bytes!"
        self.origin = "https://clinica.test:8443"
        self.claims = {
            "v": 1, "aud": "clinica-vosk-local-v1", "pid": 9301,
            "sid": "a" * 64, "question": "M02:family:diabetes", "origin": self.origin,
            "iat": 1_800_000_000, "exp": 1_800_000_030, "jti": "b" * 32,
        }

    def test_valid_single_use_ticket(self):
        guard = TicketReplayGuard.create()
        claims = guard.verify_and_consume(ticket(self.secret, self.claims), self.secret, self.origin, 1_800_000_001)
        self.assertEqual(claims["pid"], 9301)
        with self.assertRaisesRegex(ValueError, "TICKET_REPLAYED"):
            guard.verify_and_consume(ticket(self.secret, self.claims), self.secret, self.origin, 1_800_000_002)

    def test_valid_normal_application_origin(self):
        normal_origin = "https://clinica.test"
        claims = {**self.claims, "origin": normal_origin, "jti": "d" * 32}
        result = TicketReplayGuard.create().verify_and_consume(
            ticket(self.secret, claims), self.secret, normal_origin, 1_800_000_001
        )
        self.assertEqual(result["origin"], normal_origin)

    def test_rejects_tampering_wrong_origin_expiry_and_m07(self):
        guard = TicketReplayGuard.create()
        valid = ticket(self.secret, self.claims)
        for value, origin, now in [
            (valid + "x", self.origin, 1_800_000_001),
            (valid, "https://evil.test:8443", 1_800_000_001),
            (valid, self.origin, 1_800_000_030),
        ]:
            with self.assertRaises(ValueError):
                guard.verify_and_consume(value, self.secret, origin, now)
        denied = {**self.claims, "question": "M07:question:1", "jti": "c" * 32}
        with self.assertRaisesRegex(ValueError, "TICKET_INVALID"):
            guard.verify_and_consume(ticket(self.secret, denied), self.secret, self.origin, 1_800_000_001)

    def test_diagnostic_ticket_is_strict_single_use_and_cross_purpose_isolated(self):
        origin = DIAGNOSTIC_ORIGIN
        claims = {
            "v": 1, "aud": "clinica-vosk-diagnostic-v1", "purpose": "local_voice_diagnostic",
            "sid": "e" * 64, "origin": origin, "iat": 1_800_000_000,
            "exp": 1_800_000_030, "jti": "f" * 32,
        }
        guard = TicketReplayGuard.create()
        verified = guard.verify_diagnostic_and_consume(ticket(self.secret, claims), self.secret, origin, 1_800_000_001)
        self.assertEqual(verified["purpose"], "local_voice_diagnostic")
        self.assertNotIn("pid", verified)
        self.assertNotIn("question", verified)
        with self.assertRaisesRegex(ValueError, "TICKET_REPLAYED"):
            guard.verify_diagnostic_and_consume(ticket(self.secret, claims), self.secret, origin, 1_800_000_002)
        with self.assertRaisesRegex(ValueError, "TICKET_INVALID"):
            TicketReplayGuard.create().verify_and_consume(ticket(self.secret, claims), self.secret, origin, 1_800_000_001)
        clinical_like = {**claims, "pid": 9301}
        with self.assertRaisesRegex(ValueError, "DIAGNOSTIC_TICKET_INVALID"):
            TicketReplayGuard.create().verify_diagnostic_and_consume(ticket(self.secret, clinical_like), self.secret, origin, 1_800_000_001)
        with self.assertRaisesRegex(ValueError, "DIAGNOSTIC_TICKET_INVALID"):
            TicketReplayGuard.create().verify_diagnostic_and_consume(ticket(self.secret, claims), self.secret, "https://clinica.test", 1_800_000_001)

    def test_diagnostic_ticket_is_bound_to_authenticated_browser_cookie(self):
        jwt = "synthetic.jwt.token"
        sid = hmac.new(self.secret, jwt.encode("utf-8"), hashlib.sha256).hexdigest()
        claims = {"sid": sid}
        self.assertTrue(diagnostic_session_matches(claims, "CLINICAJWT=" + jwt, self.secret))
        self.assertFalse(diagnostic_session_matches(claims, "", self.secret))
        self.assertFalse(diagnostic_session_matches(claims, "CLINICAJWT=other.jwt", self.secret))

    def test_diagnostic_only_gateway_rejects_clinical_dictation_path(self):
        gateway = VoskGateway(object(), self.secret, DIAGNOSTIC_ORIGIN, diagnostic_only=True)
        self.assertTrue(gateway.route_allowed("/diagnostic"))
        self.assertFalse(gateway.route_allowed("/dictation"))
        self.assertFalse(VoskGateway(object(), self.secret, "https://clinica.test", diagnostic_only=True).route_allowed("/diagnostic"))


if __name__ == "__main__":
    unittest.main(verbosity=2)
