# LDAPS CA Certificate

Replace `ad-ca.crt` with the full PEM certificate chain that validates your AD domain controllers' LDAPS certificates.

Include:
- the issuing/root CA certificate
- any intermediate CA certificates

The Docker build copies this file to `/etc/ldap/certs/ad-ca.crt`. If it contains a real PEM certificate, it is also installed into the Debian system trust store as `/usr/local/share/ca-certificates/rockdesk-ad-ca.crt`, then `update-ca-certificates` is run.

The image also appends this to OpenLDAP config:

```text
TLS_CACERT /etc/ssl/certs/ca-certificates.crt
```

The placeholder file is intentionally not a valid certificate so local builds can proceed before LDAPS is configured.
