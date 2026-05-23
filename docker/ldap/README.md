# LDAPS CA Certificate

Replace `ad-ca.crt` with the full PEM certificate chain that validates your AD domain controllers' LDAPS certificates when you want to commit the certificate chain to this repo.

For local testing with a private certificate chain that must not be committed, create `ad-ca.local.crt` in this directory instead. It is ignored by Git and takes precedence over `ad-ca.crt` during Docker builds.

Include:
- the issuing/root CA certificate
- any intermediate CA certificates

The Docker build copies `ad-ca.local.crt` when present, otherwise `ad-ca.crt`, to `/etc/ldap/certs/ad-ca.crt` with `root:root` ownership and `0644` permissions. If it contains a real PEM certificate, it is also installed into the Debian system trust store as `/usr/local/share/ca-certificates/rockdesk-ad-ca.crt`, then `update-ca-certificates` is run.

The image also appends this to OpenLDAP config:

```text
TLS_CACERT /etc/ssl/certs/ca-certificates.crt
```

The placeholder file is intentionally not a valid certificate so local builds can proceed before LDAPS is configured.
