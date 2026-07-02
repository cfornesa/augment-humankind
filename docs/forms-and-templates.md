# Forms And Starter Templates

Forms are database-owned CMS records. The installer seeds a Contact Form and a
Newsletter Signup, and admins can edit their settings and fields under
`/admin/forms`.

Contact and ordinary forms email submissions to the configured recipient and do
not persist payloads. Newsletter Signup stores email addresses in
`newsletter_subscribers` with consent defaulting to true.

Art piece starter templates are database-owned records seeded during
installation. Pieces -> Templates edits the template metadata and HTML/CSS/JS
that Create Piece uses as its default starter code.
