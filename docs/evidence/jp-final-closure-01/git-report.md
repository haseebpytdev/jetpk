# JP-FINAL-CLOSURE-01-R4 — Git report

## START (pre-R4 ChatGPT-verified)

- PRE_R4_VERIFIED_HEAD=`9f4fb03ca8a5d387ec535c60c57bd411b022a12a` (docs/evidence tip)
- PRE_R4_ENGINEERING=`63e66e65bf8d83acaa5feaeb0efcedd66ad1f75e`
- PRE_R4_PUBLIC_BUILD=`4KN41ZZvPsqgb3xu8D7Ju`
- BRANCH=`phase/jp-flight-perf-01`
- REMOTE=`jetpk` (not `origin`)

## Commits after 9f4fb03c

| SHA | TYPE | SUBJECT | DEPLOYED |
|---|---|---|---|
| `6e3ea4e69bbd2d463aaabfe2f53d93388e29b3f9` | ENGINEERING_RUNTIME | fix(jp-r4): flight card CTA parity, Book Now timing, email semantic fields | YES |

## Authority (post-R4)

- FINAL_R4_ENGINEERING_SHA=`6e3ea4e69bbd2d463aaabfe2f53d93388e29b3f9` (deployed runtime object)
- DEPLOYED_RUNTIME_SHA=`6e3ea4e69bbd2d463aaabfe2f53d93388e29b3f9`
- PUBLIC_BUILD_ID=`OUwL6VdIoWW07Xli8W_KB`
- EVIDENCE_COMMIT_SHA=(filled after docs commit)
- FINAL_BRANCH_HEAD_SHA=(filled after docs commit)

## Notes

- `JetpkEmailSampleDataProvider` preview scalars may land in a follow-up commit; live semantic profiles already shipped in `6e3ea4e6`.
- Evidence commit follows separately so engineering SHA ≠ branch tip after docs land.