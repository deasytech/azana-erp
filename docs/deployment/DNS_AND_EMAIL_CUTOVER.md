# DNS and e-mail cutover

**Goal:** serve the website, ERP and API from the VPS while the company's e-mail on Syskay cPanel keeps working without a single interruption.

**Rules:** no nameserver change (the zone stays where it is); no MX, SPF, DKIM, DMARC or mail-host record is edited unless the owner approves a specific change after section 2; no DNS is edited by the author of this pack. `<VPS_IPV4>` and `<VPS_IPV6>` are placeholders: they are not known yet and must not be invented.

## 1. Who edits the zone

Find out where the zone is served (`dig NS azanafarms.com +short`). If it is Syskay's nameservers, the zone is edited in Syskay's cPanel "Zone Editor" (or by their support). **Needed from the company:** that access, and a screenshot or export of the whole current zone.

## 2. Read the zone first (nothing is changed in this step)

Run these from any machine and save the output with the date:

```bash
dig +short NS    azanafarms.com
dig +noall +answer A azanafarms.com ; dig +noall +answer AAAA azanafarms.com
dig +noall +answer A www.azanafarms.com ; dig +noall +answer CNAME www.azanafarms.com
dig +noall +answer MX azanafarms.com
dig +noall +answer TXT azanafarms.com                       # SPF is the "v=spf1" record
dig +noall +answer TXT _dmarc.azanafarms.com
dig +noall +answer TXT default._domainkey.azanafarms.com    # also try the selector names in the cPanel mail-deliverability page
for h in mail webmail cpanel autodiscover autoconfig smtp imap pop pop3 ftp; do dig +noall +answer $h.azanafarms.com; done
dig +noall +answer A erp.azanafarms.com ; dig +noall +answer A api.azanafarms.com     # should be empty: these are new
```

### The three traps (each must be ruled out in writing before the bare domain or `www` is moved)

| Trap | How it shows | What goes wrong | If found |
|---|---|---|---|
| **A. MX points at the bare domain** | `MX 0 azanafarms.com.`, or the MX host is a CNAME to it | Moving the bare domain's address sends incoming mail to the VPS, which has no mail server: mail bounces | Ask Syskay for their mail host name. Add/keep an A record for `mail` pointing at **Syskay's** IP, and change MX to it **before** the bare domain moves. This is an MX edit: needs owner approval |
| **B. Mail names are CNAMEs to the bare domain** | `webmail`, `cpanel`, `autodiscover`, `autoconfig`, `mail`, `smtp` resolve through `azanafarms.com` | Webmail and phone mail settings stop working | Replace each with an A record to Syskay's IP (or a CNAME to Syskay's server name), with approval |
| **C. SPF authorises "a" or "mx" instead of an IP** | `v=spf1 +a +mx ...` with no `ip4:` for Syskay | `a` means "the address of azanafarms.com". After the move that is the VPS, not Syskay, so company mail starts failing SPF and lands in spam or is rejected | Add `ip4:<Syskay IP>` to the existing SPF record before the move. This is an SPF edit: needs owner approval |

Also confirm no cPanel-hosted service other than the old website uses the bare domain's address (e.g. an FTP client, a monitoring check, an old subdomain pointing at it by CNAME). Anything left that should keep pointing at Syskay needs its own record to Syskay's IP.

## 3. Records to add (phase 1: no risk to the website or e-mail)

New names; they did not exist, so nothing can be broken.

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `erp` | `<VPS_IPV4>` | 300 |
| A | `api` | `<VPS_IPV4>` | 300 |
| AAAA | `erp`, `api` | `<VPS_IPV6>` only if the VPS has IPv6 and nginx listens on it (it does in `azana.conf`) | 300 |

Then, on the server: certificate for `erp` + `api`, `deploy.sh` first run, `VERIFICATION_CHECKLIST.md` on the ERP and API. Point a phone at `https://api.azanafarms.com/api/v1`.

**Test the website before it goes live** without DNS, from your own computer:

```bash
curl -I --resolve www.azanafarms.com:80:<VPS_IPV4> http://www.azanafarms.com/        # port 80 only until the certificate exists
# or add "<VPS_IPV4> www.azanafarms.com azanafarms.com" to your own hosts file temporarily (and remove it afterwards)
```

## 4. Website cutover (phase 2: the only step that can affect the live website)

Pre-conditions (all must be true): section 2 done and traps A, B, C ruled out or fixed with approval; ERP and API verified; the new website content approved; off-site backup working.

1. **24 hours before:** lower the TTL of the bare domain's and `www`'s A (and CNAME) records to 300 seconds. Do not touch other records' TTL.
2. Choose a quiet time. Note the current values (screenshot) so they can be restored.
3. Change `A azanafarms.com` to `<VPS_IPV4>`. Change `www` to `A <VPS_IPV4>` (or leave it as a CNAME to the bare domain if that is how it is now).
4. Immediately: `sudo certbot certonly --webroot -w /var/www/certbot -d azanafarms.com -d www.azanafarms.com`, then install the full `azana.conf` (guide §7). Until the certificate exists, the site answers on http only, for a few minutes.
5. Verify: website over https, the bare domain redirects to `www`, `robots.txt` and `sitemap.xml`, the contact form; **and, in the next hour: send an e-mail to a company address from an outside account and receive it; send one out from webmail and check the headers show SPF `pass` and DKIM `pass`.**
6. After a quiet week, raise the TTL back (3600 or the previous value).

**Rollback (website only):** set the bare domain's and `www`'s records back to the values noted in step 2. With a 300-second TTL the old site is back within minutes. Nothing else needs to change; ERP and API stay on the VPS. E-mail records are never part of the rollback because none was edited (unless a trap fix was approved, in which case that edit is permanent and harmless).

## 5. Sending mail from the ERP

The ERP sends alerts and task notices. Send them through the **existing company mail host** using a dedicated mailbox (suggested `erp@azanafarms.com`) over authenticated SMTP (port 587 STARTTLS or 465 TLS). Mail then leaves from Syskay's server, already covered by the existing SPF and DKIM, so **no e-mail DNS record changes**.

Needed from the company: the mailbox, its password, the outgoing server name and port, and confirmation that Syskay allows SMTP logins from outside its own network (some hosts restrict them by IP; if so, ask Syskay to allow `<VPS_IPV4>`). Do not send mail directly from the VPS: most VPS plans block port 25, and it would need SPF/DKIM/PTR changes.

Test: `php artisan tinker`, `Mail::raw('test', fn ($m) => $m->to('<YOUR_ADDRESS>')->subject('ERP test'));`, then check the message arrives and the headers pass SPF and DKIM.

## 6. Final record sheet (to fill in when the values are known)

| Type | Name | Value | Phase | Edited by |
|---|---|---|---|---|
| A | `erp` | `<VPS_IPV4>` | 1 | |
| A | `api` | `<VPS_IPV4>` | 1 | |
| A | `@` (azanafarms.com) | `<VPS_IPV4>` | 2 | |
| A / CNAME | `www` | `<VPS_IPV4>` or CNAME to `@` | 2 | |
| MX, TXT (SPF, DKIM, DMARC), `mail`, `webmail`, `cpanel`, `autodiscover`, `autoconfig` | | **unchanged** unless a trap fix was approved | | |

Optional later, with approval: a CAA record (`0 issue "letsencrypt.org"`).
