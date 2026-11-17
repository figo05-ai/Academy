# Academy Contabo CI/CD v2.0 — Safe application

Read `README-START-HERE.md` and `deployment/CONTABO-DEPLOY-SETUP.md` before applying.

- Apply **locally** to the existing branch `ops/contabo-ghcr-deploy` in `~/Academy-Contabo`.
- This archive replaces the previous `academy-contabo-cicd-package.zip` package. The older version lacked a CD activation gate and its rollback handling was incomplete.
- **Never** unzip this archive on the production VPS.
- **Never** merge this into main and enable CD in the same unreviewed step.
- **Never** add real `.env`, SSH private keys or S3/Restic secrets to Git.

Use this preview/validation from a **local** terminal:

```bash
cd ~/Academy-Contabo
git branch --show-current
unzip -l ~/Downloads/academy-contabo-cicd-v2.zip
unzip -o ~/Downloads/academy-contabo-cicd-v2.zip
chmod +x deployment/academy-deploy.sh
bash -n deployment/academy-deploy.sh
git diff --check
git status --short
```

The workflow runs CI on PRs; production deployment is explicitly **disabled by default** unless the GitHub repository Actions Variable `CONTABO_DEPLOY_ENABLED` is exactly `true`.
