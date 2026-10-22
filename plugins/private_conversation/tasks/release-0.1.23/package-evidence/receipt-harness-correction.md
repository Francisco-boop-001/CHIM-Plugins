# Receipt harness correction

The first host package gate ran package_check.py successfully (4 tests) and built all five assets, but PowerShell rejected the lead's ConvertTo-Json -Depth4 parameter. No valid JSON receipts were produced; its claimed hash comparison was not accepted. This was an evidence harness error, not a package or product failure. The corrected gate uses -Depth 4 and terminating errors, records before/after hashes, and rebuilds the same candidate to establish the frozen-source claim.

The corrected harness next hit the builder's intentional new-or-empty-directory guard. The first candidate was preserved under dist/release-v0.1.23-first-harness; the rejected invocation is retained as occupied-output-package-build.*. A fresh candidate then built successfully and all five files equaled the first build. Package checks were not rerun after their recorded four-case success. The before/after 21-file hashes match.
